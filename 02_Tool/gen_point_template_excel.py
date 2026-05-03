from pathlib import Path
import racedaystools
import pandas as pd
from openpyxl.styles import Font
import argparse


def to_template_file(df, template_filename):
    with pd.ExcelWriter(template_filename, mode="w") as writer:
        for group_name, df_group in df.groupby(["renn_ort", "wertungskategorie"]):
            ws_name = f"{group_name[0]}_{group_name[1]}"
            df_group["startnummer"] = ""
            df_group["transpondernummer"] = ""

            df_tmp = df_group[
                [
                    "rundenanzahl",
                    "wertungstyp",
                    "position",
                    "startnummer",
                    "transpondernummer",
                ]
            ]
            df_tmp.to_excel(writer, sheet_name=ws_name, index=False, startrow=2)

            ws = writer.sheets[ws_name]
            ws["A1"] = ws_name
            ws["A1"].font = Font(bold=True, sz=14)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Create the template to fill in he points Results from the points Overview"
    )

    parser.add_argument(
        "-config_path", type=str, default="config.yaml", help="path to the config file"
    )
    args = parser.parse_args()

    cfg = racedaystools.read_setup_yaml(args.config_path)

    df = racedaystools.read_point_score_file(
        cfg["PointsExcel"]["raw_point_excel_path"],
        race_locations=cfg["PointsExcel"]["race_locations"],
        punktexcel2wertungskategorie=cfg["PointsExcel"][
            "excel_rating_category2rating_category"
        ],
    )
    to_template_file(df, cfg["PointsExcel"]["manual_result_template"])
