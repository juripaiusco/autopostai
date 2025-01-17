from task.posts_send import posts_send
from task.comments_get import comments_get

def main():
    debug = True
    posts_send(debug=debug)

    if debug is True:
        print()

    comments_get(debug=debug)

if __name__ == "__main__":
    main()
