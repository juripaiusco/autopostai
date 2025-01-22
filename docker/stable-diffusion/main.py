from cmd import PROMPT
from datetime import datetime
import pytz
from tqdm import tqdm
from lib.huggingface import HuggingFace
import argparse

# Configura argparse per accettare il prompt come argomento
parser = argparse.ArgumentParser(description="Genera un'immagine da un prompt.")
parser.add_argument('--prompt', type=str, required=True, help='Il prompt per generare l\'immagine')

args = parser.parse_args()

# Usa il prompt
PROMPT = args.prompt

NUM_INFERENCE_STEPS = 40

# Fuso orario locale (ad esempio, Europa/Roma)
LOCAL_TIMEZONE = pytz.timezone('Europe/Rome')

# Ottieni la data attuale
CURRENT_TIME = datetime.now(LOCAL_TIMEZONE).strftime('%Y%m%d%H%M%S')

def main():
    huggingface = HuggingFace()

    progress_bar_desc = "SD-3.5"
    data_list = range(1)

    with tqdm(total=len(data_list), desc=progress_bar_desc, ncols=None) as progress_bar:
        for i in data_list:
            progress_bar.set_description(f"{progress_bar_desc} - genero immagine: {i + 1}")
            image_name = huggingface.stableDiffusion_generate_img(
                prompt=PROMPT,
                num_inference_steps=NUM_INFERENCE_STEPS,
                img_name=f"{CURRENT_TIME}-{i + 1}"
            )
            progress_bar.update(1)
        print(image_name)


if __name__ == '__main__':
    main()
