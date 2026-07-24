"""Colori a schermo attivi solo su TTY reale, spenti su file/pipe/NO_COLOR."""

import logging

from publisher import cli_output


def test_use_color_false_when_not_a_tty(monkeypatch):
    monkeypatch.delenv("NO_COLOR", raising=False)
    monkeypatch.setattr(cli_output.sys.stdout, "isatty", lambda: False)
    assert cli_output.use_color() is False


def test_use_color_true_when_tty_and_no_color_unset(monkeypatch):
    monkeypatch.delenv("NO_COLOR", raising=False)
    monkeypatch.setattr(cli_output.sys.stdout, "isatty", lambda: True)
    assert cli_output.use_color() is True


def test_use_color_false_when_no_color_env_set_even_on_tty(monkeypatch):
    monkeypatch.setenv("NO_COLOR", "1")
    monkeypatch.setattr(cli_output.sys.stdout, "isatty", lambda: True)
    assert cli_output.use_color() is False


def test_task_start_end_print_task_name(monkeypatch, capsys):
    monkeypatch.setattr(cli_output.sys.stdout, "isatty", lambda: False)
    started = cli_output.task_start("posts_send")
    cli_output.task_end("posts_send", started)
    out = capsys.readouterr().out
    assert "posts_send" in out
    assert "\033[" not in out  # niente ANSI grezzo quando i colori sono spenti


def test_task_end_indents_so_it_reads_as_nested_under_the_header(monkeypatch, capsys):
    # Niente piu' box a larghezza variabile (bug precedente): l'header e' a
    # bandiera, il footer e' indentato con lo stesso prefisso dei log del task.
    monkeypatch.setattr(cli_output.sys.stdout, "isatty", lambda: False)
    started = cli_output.task_start("task_complete")
    cli_output.task_end("task_complete", started)
    lines = [l for l in capsys.readouterr().out.splitlines() if l]
    assert lines[0].startswith("▶ task_complete")
    assert lines[1].startswith(cli_output.LOG_INDENT)


def test_color_formatter_wraps_error_lines_when_color_enabled(monkeypatch):
    monkeypatch.delenv("NO_COLOR", raising=False)
    monkeypatch.setattr(cli_output.sys.stdout, "isatty", lambda: True)

    formatter = cli_output.ColorFormatter("%(levelname)s %(message)s")
    record = logging.LogRecord("test", logging.ERROR, __file__, 1, "boom", None, None)
    formatted = formatter.format(record)

    assert "\033[31m" in formatted  # rosso
    assert "boom" in formatted


def test_color_formatter_leaves_info_lines_uncolored(monkeypatch):
    monkeypatch.delenv("NO_COLOR", raising=False)
    monkeypatch.setattr(cli_output.sys.stdout, "isatty", lambda: True)

    formatter = cli_output.ColorFormatter("%(levelname)s %(message)s")
    record = logging.LogRecord("test", logging.INFO, __file__, 1, "tutto ok", None, None)
    formatted = formatter.format(record)

    assert "\033[" not in formatted
