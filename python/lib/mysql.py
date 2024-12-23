import os
from dotenv import load_dotenv

load_dotenv()

def test():
    print(os.getenv("DB_HOST"))

if __name__ == "__main__":
  test()
