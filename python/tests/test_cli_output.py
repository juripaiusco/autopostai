"""Colori a schermo attivi solo su TTY reale, spenti su file/pipe/NO_COLOR."""

import logging
import sys

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


def _no_color(monkeypatch):
    monkeypatch.delenv("NO_COLOR", raising=False)
    monkeypatch.setattr(cli_output.sys.stdout, "isatty", lambda: False)


def _record(level, msg, exc_info=None):
    return logging.LogRecord("publisher.tasks.x", level, __file__, 1, msg, None, exc_info)


def test_task_without_logs_prints_header_and_footer(monkeypatch, capsys):
    _no_color(monkeypatch)
    started = cli_output.task_start("posts_send")
    cli_output.task_end("posts_send", started)
    lines = [l for l in capsys.readouterr().out.splitlines() if l]
    assert lines[0] == "▶ posts_send"
    assert lines[1].startswith(cli_output.LOG_INDENT + "↳ posts_send completato")
    assert all("\033[" not in l for l in lines)  # niente ANSI con colori spenti


def test_first_task_log_line_becomes_the_header(monkeypatch, capsys):
    _no_color(monkeypatch)
    formatter = cli_output.CompactFormatter()
    started = cli_output.task_start("posts_send")
    first = formatter.format(_record(logging.INFO, "posts_send: 0 post da valutare"))
    second = formatter.format(_record(logging.INFO, "posts_send: post 12 canale 'fb' - pubblicato"))
    cli_output.task_end("posts_send", started)

    assert first == "▶ posts_send: 0 post da valutare"
    # righe successive: indentate e senza il prefisso del task (gia' in testa)
    assert second == cli_output.LOG_INDENT + "post 12 canale 'fb' - pubblicato"
    # intestazione gia' stampata: task_end non la ripete
    assert "▶" not in capsys.readouterr().out


def test_error_as_first_line_keeps_a_separate_header(monkeypatch):
    _no_color(monkeypatch)
    formatter = cli_output.CompactFormatter()
    started = cli_output.task_start("reply_send")
    out = formatter.format(_record(logging.ERROR, "reply_send: risposta fallita"))
    cli_output.task_end("reply_send", started)

    header, line = out.splitlines()
    assert header == "▶ reply_send"
    assert line == cli_output.LOG_INDENT + "✘ ERROR risposta fallita"


def test_exception_traceback_is_indented_under_the_error(monkeypatch):
    _no_color(monkeypatch)
    formatter = cli_output.CompactFormatter()
    try:
        raise ValueError("boom")
    except ValueError:
        exc_info = sys.exc_info()
    out = formatter.format(_record(logging.ERROR, "task 'x' fallito", exc_info))

    lines = out.splitlines()
    assert lines[0] == cli_output.LOG_INDENT + "✘ ERROR task 'x' fallito"
    assert all(l.startswith(cli_output.LOG_INDENT + "  ") for l in lines[1:])
    assert "ValueError: boom" in lines[-1]


def test_warning_outside_task_is_indented_with_level(monkeypatch):
    _no_color(monkeypatch)
    out = cli_output.CompactFormatter().format(_record(logging.WARNING, "worker: run in corso"))
    assert out == cli_output.LOG_INDENT + "⚠ WARNING worker: run in corso"


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
