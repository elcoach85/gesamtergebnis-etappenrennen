import argparse
import logging

import pandas as pd
import racedaystools


def read_heuer_results(cfg):
    logging.info("Start Reading heuer results")
    results_cfg = cfg["ResultFilesSetup"]

    dfs = []

    for race_name, results_path in results_cfg["race_name2result_file"].items():
        df = racedaystools.extract_race_result_from_ws(
            results_path,
            list(results_cfg["excel_rating_category2rating_category"].keys()),
            results_cfg["time_for_not_start_in_s"],
        )
        df["renn_ort"] = race_name
        dfs.append(df)

    df = pd.concat(dfs)
    df["wertungskategorie"] = df["wertungskategorie"].replace(
        results_cfg["excel_rating_category2rating_category"]
    )

    col_to_keep = list(results_cfg["excel2sql_colname"].keys())
    df = df[col_to_keep]
    df = df.rename(columns=results_cfg["excel2sql_colname"])
    
    return df


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Try to import all files into the database"
    )
    parser.add_argument(
        "-config_path", type=str, default="config.yaml", help="path to the config file"
    )
    args = parser.parse_args()

    cfg = racedaystools.read_setup_yaml(args.config_path)

    # setup logging
    logging.basicConfig()
    logging_str = cfg["Basics"]["loglevel"]
    level = logging.getLevelName(logging_str)
    logging.getLogger().setLevel(level)

    # Create database engine
    db_engine = racedaystools.get_sql_engine(cfg)

    # Import the wertungen into the database
    df_points = racedaystools.read_point_score_file(
        cfg["PointsExcel"]["raw_point_excel_path"],
        race_locations=cfg["PointsExcel"]["race_locations"],
        punktexcel2wertungskategorie=cfg["PointsExcel"][
            "excel_rating_category2rating_category"
        ],
    )
    with db_engine.connect() as conn:
        df_points.to_sql("punkte_wertung", conn, if_exists="replace", index=False)

    # Import Results into DB
    df_results = read_heuer_results(cfg)
    with db_engine.connect() as conn:
        df_results.to_sql("ergebnis", conn, if_exists="replace", index=False)

    # Import Points and bonus Second Results into db
    df_points = racedaystools.read_template_score_file(
        cfg["PointsExcel"]["manual_result"]
    )
    with db_engine.connect() as conn:
        # Substitute o the tranponder can be dropped
        df_participants = pd.read_sql(
            "SELECT transpondernummer,startnummer FROM teilnehmer", conn
        )
        transpondernummer2startnummer = dict(
            zip(df_participants["transpondernummer"], df_participants["startnummer"])
        )
        df_points.loc[df_points["startnummer"].isna(), "startnummer"] = df_points.loc[
            df_points["startnummer"].isna(), "transpondernummer"
        ].apply(lambda el: transpondernummer2startnummer[el])
        df_points = df_points.drop(
            columns=["transpondernummer"],
        )
        df_points.to_sql("punkte_ergebnis", conn, if_exists="replace", index=False)
