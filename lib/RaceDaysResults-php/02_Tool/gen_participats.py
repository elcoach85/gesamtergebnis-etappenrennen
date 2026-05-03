import pandas as pd
import racedaystools
import argparse

if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Create the template to fill in he points Results from the points Overview"
    )

    parser.add_argument(
        "-config_path", type=str, default="config.yaml", help="path to the config file"
    )
    parser.add_argument(
        "-participant_path",
        type=str,
        default="../01_Data/setup/participants-database-15--Juni-2022-2.csv",
        help="path to the participant file",
    )
    args = parser.parse_args()

    cfg = racedaystools.read_setup_yaml(args.config_path)
    df = pd.read_csv(args.participant_path)
    df["renn_kategorie"] = df["fahrer_kategorie"].replace(
        cfg["StarterAPISetup"]["rider_category2rating_category"]
    )

    db_engine = racedaystools.get_sql_engine(cfg)

    with db_engine.connect() as conn:
        df.to_sql("teilnehmer", conn, if_exists="replace", index=False)
