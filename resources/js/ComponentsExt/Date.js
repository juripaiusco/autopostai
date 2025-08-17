import {now} from "es-toolkit/compat";

export function __date(dateString, format = '') {

    const date = new Date(dateString);
    // Then specify how you want your dates to be formatted
    let y = new Intl.DateTimeFormat('default', { year: 'numeric' }).format(date);
    let m = new Intl.DateTimeFormat('default', { month: '2-digit' }).format(date);
    let d = new Intl.DateTimeFormat('default', { day: '2-digit' }).format(date);
    let h = new Intl.DateTimeFormat('default', { hour: '2-digit' }).format(date);
    let i = new Intl.DateTimeFormat('default', { minute: '2-digit' }).format(date);
    let s = new Intl.DateTimeFormat('default', { second: '2-digit' }).format(date);

    if (i < 10) {
        i = '0' + i;
    }

    if (s < 10) {
        s = '0' + s;
    }

    if (dateString != null) {

        switch (format) {
            case 'day':
                return d + '/' + m + '/' + y;
            case 'hour':
                return h + ':' + i;
            case 'date':
                return y + '-' + m + '-' + d;
            case 'y':
                return y;
            case 'm':
                return m;
            case 'n':
                return parseInt(m);
            case 'datestring':
                return y + '-' + m + '-' + d + ' ' + h + ':' + i + ':' + s;
            case 'diff':
                const nowDate = new Date(now());
                const targetDate = new Date(dateString);

                // Stesso anno, mese, giorno, ora e minuto
                if (
                    nowDate.getFullYear() === targetDate.getFullYear() &&
                    nowDate.getMonth() === targetDate.getMonth() &&
                    nowDate.getDate() === targetDate.getDate() &&
                    nowDate.getHours() === targetDate.getHours() &&
                    nowDate.getMinutes() === targetDate.getMinutes()
                ) {
                    return "Adesso";
                }

                // Stesso giorno → calcola differenza
                if (
                    nowDate.getFullYear() === targetDate.getFullYear() &&
                    nowDate.getMonth() === targetDate.getMonth() &&
                    nowDate.getDate() === targetDate.getDate()
                ) {
                    const diffSec = Math.floor((nowDate - targetDate) / 1000);

                    if (diffSec < 60) {
                        return diffSec + " sec fa";
                    } else {
                        const diffMin = Math.floor(diffSec / 60);
                        return diffMin + " min fa";
                    }
                }

                // Giorno diverso → mostra solo la data
                return __date(dateString, 'day');
            default:
                return d + '/' + m + '/' + y + ' ' + h + ':' + i + ':' + s;
        }

    }

    return null;
}
