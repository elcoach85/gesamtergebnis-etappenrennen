from typing import Dict
import yaml


def read_setup_yaml(path: str) -> Dict[str, str]:

    with open(path, "r", encoding="utf8") as f:
        try:
            res = yaml.safe_load(f)
        except yaml.YAMLError as exc:
            print(exc)

        return res


def get_sql_engine(cfg):
    from sqlalchemy import create_engine

    user = cfg["DbConnection"]["user"]
    pw = cfg["DbConnection"]["password"]
    host = cfg["DbConnection"]["host"]
    db_name = cfg["DbConnection"]["name"]
    sqlEngine = create_engine(f"mysql+pymysql://{user}:{pw}@{host}/{db_name}")
    return sqlEngine
