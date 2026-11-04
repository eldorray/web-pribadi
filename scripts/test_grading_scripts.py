#!/usr/bin/env python3
"""Self-check for the grading scripts. Run: python3 scripts/test_grading_scripts.py"""

import csv
import importlib.util
import os
import stat
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

import openpyxl

HERE = Path(__file__).resolve().parent


def load(name):
    spec = importlib.util.spec_from_file_location(name.replace("-", "_"), HERE / f"{name}.py")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


download = load("grading-download")
fill = load("grading-fill")

CRITERIA = ["(30) Pelafalan ", "IPA"]


def make_workbook(path):
    wb = openpyxl.Workbook()
    rubric = wb.active
    rubric.title = "Rubrik"
    rubric["A1"] = "Bukan sheet kelas"

    ws = wb.create_sheet("3B")
    ws["A1"] = "Kelas"
    ws.append([])
    ws["A4"], ws["B4"], ws["C4"] = "NIM", "Nama Mahasiswa", "Link Project UTS"
    ws["D4"], ws["E4"], ws["F4"] = CRITERIA[0], CRITERIA[1], "Total Score"
    rows = [
        (222001, "AYU / LESTARI", " https://youtu.be/aaa?si=1 "),
        (222002, "BUDI", "PROJECT SAYA"),
        (222003, "CITRA", "bukan link"),
        (222004, "DEWI", "--exec=calc"),
    ]
    for offset, (nim, name, link) in enumerate(rows):
        row = 5 + offset
        ws[f"A{row}"], ws[f"B{row}"], ws[f"C{row}"] = nim, name, link
        ws[f"F{row}"] = f"=SUM(D{row}:E{row})"
    ws["C6"].hyperlink = "https://www.youtube.com/watch?v=bbb"
    wb.save(path)


def write_csv(path, rows):
    with open(path, "w", newline="", encoding="utf-8-sig") as handle:
        writer = csv.writer(handle)
        writer.writerow(["Nama", "Pelafalan", "IPA", "Total", "Skala 0-100", "Catatan", "Status"])
        writer.writerows(rows)


class DownloadTest(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.TemporaryDirectory()
        self.root = Path(self.dir.name)
        self.xlsx = self.root / "kelas.xlsx"
        make_workbook(self.xlsx)

        # A stand-in yt-dlp that records its arguments and writes the output file.
        bin_dir = self.root / "bin"
        bin_dir.mkdir()
        self.calls = self.root / "calls.txt"
        fake = bin_dir / "yt-dlp"
        fake.write_text(
            "#!/usr/bin/env python3\n"
            "import sys\n"
            f"open({str(self.calls)!r}, 'a').write(' '.join(sys.argv[1:]) + '\\n')\n"
            "if sys.argv[-1].endswith('bbb'):\n"
            "    print('ERROR: Video unavailable', file=sys.stderr); sys.exit(1)\n"
            "open(sys.argv[sys.argv.index('-o') + 1], 'w').write('video')\n"
        )
        fake.chmod(fake.stat().st_mode | stat.S_IEXEC)
        self.env = {**os.environ, "PATH": f"{bin_dir}{os.pathsep}{os.environ['PATH']}"}
        self.out = self.root / "video"

    def tearDown(self):
        self.dir.cleanup()

    def run_script(self, *extra):
        return subprocess.run(
            [sys.executable, str(HERE / "grading-download.py"), str(self.xlsx), "--out", str(self.out), *extra],
            env=self.env, capture_output=True, text=True,
        )

    def test_reads_class_sheets_only_and_prefers_the_hyperlink(self):
        students = list(download.read_students(self.xlsx))

        self.assertEqual({s.sheet for s in students}, {"3B"})
        self.assertEqual(students[0].link, "https://youtu.be/aaa?si=1")
        self.assertEqual(students[1].link, "https://www.youtube.com/watch?v=bbb")

    def test_names_files_by_nim_and_reports_failures(self):
        result = self.run_script()

        self.assertEqual(result.returncode, 1, result.stderr)
        self.assertTrue((self.out / "3B" / "222001 - AYU - LESTARI.mp4").exists())

        with open(self.out / "gagal.csv", encoding="utf-8-sig", newline="") as handle:
            failed = {row["NIM"]: row["Alasan"] for row in csv.DictReader(handle)}

        self.assertEqual(set(failed), {"222002", "222003", "222004"})
        self.assertIn("unavailable", failed["222002"])
        self.assertIn("link", failed["222003"].lower())

    def test_never_passes_a_cell_to_yt_dlp_as_an_option(self):
        self.run_script()

        calls = self.calls.read_text()
        self.assertNotIn("--exec", calls)
        self.assertIn(" -- https://youtu.be/aaa?si=1", calls)

    def test_a_second_run_skips_finished_videos(self):
        self.run_script()
        first = self.calls.read_text().count("aaa")
        self.run_script()

        self.assertEqual(self.calls.read_text().count("aaa"), first)

    def test_dry_run_downloads_nothing(self):
        result = self.run_script("--dry-run")

        self.assertFalse(self.calls.exists())
        self.assertIn("222001", result.stdout)


class FillTest(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.TemporaryDirectory()
        self.root = Path(self.dir.name)
        self.xlsx = self.root / "kelas.xlsx"
        self.csv = self.root / "nilai.csv"
        self.out = self.root / "hasil.xlsx"
        make_workbook(self.xlsx)

    def tearDown(self):
        self.dir.cleanup()

    def run_script(self, *extra):
        return subprocess.run(
            [sys.executable, str(HERE / "grading-fill.py"), str(self.xlsx), str(self.csv), "--out", str(self.out), *extra],
            capture_output=True, text=True,
        )

    def test_fills_by_nim_regardless_of_row_order(self):
        write_csv(self.csv, [
            ["222002 - BUDI", "20", "9.5", "29.5", "59", "", "disetujui"],
            ["222001 - AYU - LESTARI", "25", "10", "35", "70", "", "disetujui"],
        ])

        result = self.run_script()

        self.assertEqual(result.returncode, 0, result.stderr)
        ws = openpyxl.load_workbook(self.out)["3B"]
        self.assertEqual((ws["D5"].value, ws["E5"].value), (25, 10))
        self.assertEqual((ws["D6"].value, ws["E6"].value), (20, 9.5))

    def test_keeps_formulas_hyperlinks_and_the_original_file(self):
        write_csv(self.csv, [["222001 - AYU", "25", "10", "35", "70", "", "disetujui"]])
        before = self.xlsx.read_bytes()

        self.run_script()

        ws = openpyxl.load_workbook(self.out)["3B"]
        self.assertEqual(ws["F5"].value, "=SUM(D5:E5)")
        self.assertEqual(ws["C6"].hyperlink.target, "https://www.youtube.com/watch?v=bbb")
        self.assertEqual(self.xlsx.read_bytes(), before)

    def test_unapproved_rows_are_left_empty(self):
        write_csv(self.csv, [["222001 - AYU", "", "", "", "", "", "belum disetujui"]])

        result = self.run_script()

        self.assertIsNone(openpyxl.load_workbook(self.out)["3B"]["D5"].value)
        self.assertIn("222001", result.stdout)

    def test_unknown_nim_is_reported(self):
        write_csv(self.csv, [["999999 - ORANG LAIN", "25", "10", "35", "70", "", "disetujui"]])

        self.assertIn("999999", self.run_script().stdout)

    def test_existing_scores_are_not_overwritten_without_the_flag(self):
        wb = openpyxl.load_workbook(self.xlsx)
        wb["3B"]["D5"] = 1
        wb.save(self.xlsx)
        write_csv(self.csv, [["222001 - AYU", "25", "10", "35", "70", "", "disetujui"]])

        result = self.run_script()

        self.assertEqual(openpyxl.load_workbook(self.out)["3B"]["D5"].value, 1)
        self.assertIn("sudah terisi", result.stdout)

        self.run_script("--overwrite")
        self.assertEqual(openpyxl.load_workbook(self.out)["3B"]["D5"].value, 25)

    def test_missing_criterion_column_stops_before_writing(self):
        write_csv(self.csv, [["222001 - AYU", "25", "10", "35", "70", "", "disetujui"]])
        wb = openpyxl.load_workbook(self.xlsx)
        wb["3B"]["E4"] = "Kriteria lain"
        wb.save(self.xlsx)

        result = self.run_script()

        self.assertEqual(result.returncode, 1)
        self.assertIn("IPA", result.stderr)
        self.assertFalse(self.out.exists())

    def test_refuses_to_write_over_the_source(self):
        write_csv(self.csv, [])

        result = subprocess.run(
            [sys.executable, str(HERE / "grading-fill.py"), str(self.xlsx), str(self.csv), "--out", str(self.xlsx)],
            capture_output=True, text=True,
        )

        self.assertEqual(result.returncode, 2)


if __name__ == "__main__":
    unittest.main()
