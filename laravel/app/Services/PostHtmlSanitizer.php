<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;

/**
 * Pulisce l'HTML dell'editor ricco (anteprima per WordPress e newsletter)
 * prima di salvarlo: esce su siti e caselle email di terzi e viene mostrato
 * nella pagina del post, quindi passano solo i tag e gli stili che l'editor
 * produce. Tutto il resto (script, eventi on*, iframe, stili di
 * posizionamento) viene tolto.
 *
 * Gli shortcode nei link (`href="[WordPress url id=&quot;12&quot;]"`: le
 * virgolette dentro l'attributo sono codificate) non sono URL validi e
 * HTMLPurifier li scarterebbe: vengono messi da parte prima della pulizia e
 * rimessi dopo, codificati una sola volta. Il worker li risolve all'uscita.
 */
class PostHtmlSanitizer
{
    private const ALLOWED = 'h1[style],h2[style],h3[style],p[style],br,hr,strong,b,em,i,u,s,'
        .'ul,ol,li,blockquote,a[href|target],span[style]';

    /** Segnaposto (URL valido) al posto degli shortcode negli href. */
    private const PLACEHOLDER = 'https://shortcode.faper3.invalid/';

    private ?HTMLPurifier $purifier = null;

    public function clean(string $html): string
    {
        $shortcodes = [];
        $html = preg_replace_callback(
            '/href=(["\'])\s*(\[[^\]"\'<>]+\])\s*\1/i',
            function (array $m) use (&$shortcodes) {
                $shortcodes[] = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5);

                return 'href="'.self::PLACEHOLDER.(count($shortcodes) - 1).'"';
            },
            $html,
        );

        $clean = $this->purifier()->purify($html);

        return preg_replace_callback(
            '#'.preg_quote(self::PLACEHOLDER, '#').'(\d+)#',
            fn (array $m) => htmlspecialchars($shortcodes[(int) $m[1]] ?? '', ENT_QUOTES),
            $clean,
        );
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier) {
            return $this->purifier;
        }

        $cache = storage_path('framework/cache/htmlpurifier');
        File::ensureDirectoryExists($cache);

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $cache);
        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('CSS.AllowedProperties', ['color', 'text-align']);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('AutoFormat.RemoveEmpty', false);

        return $this->purifier = new HTMLPurifier($config);
    }
}
