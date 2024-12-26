import os
import mysql.connector
from mysql.connector import Error

class Mysql:
    def __init__(self):

        self.CONNECTION = None
        self.CURSOR = None


    def connect(self):

        try:

            self.CONNECTION = mysql.connector.connect(
                host=os.getenv("DB_HOST"),
                user=os.getenv("DB_USERNAME"),
                password=os.getenv("DB_PASSWORD"),
                database=os.getenv("DB_DATABASE"),
                port=int(os.getenv("DB_PORT")),
            )

            if self.CONNECTION.is_connected():
                print("Connection to DB is open.")
                self.CURSOR = self.CONNECTION.cursor(dictionary=True)

        except Error as e:
            print(f"Error connection: {e}")


    def close(self):

        if self.CONNECTION.is_connected():
            self.CURSOR.close()
            self.CONNECTION.close()
            print("Connection to DB is closed.")


    def query(self, query, parameters=None):

        try:
            self.CURSOR.execute(query, parameters)
            return self.CURSOR.fetchall()

        except Error as e:
            print(f"Error query: {e}")


    def test(self):

        self.connect()
        # self.query("SELECT * FROM autopostai_posts WHERE published IS NULL")
        self.close()
