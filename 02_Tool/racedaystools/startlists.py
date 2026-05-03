import http.client
import json
from typing import Dict, List
import pandas as pd


def fetch_startertlist(
    rider_category2rating_category,
    url="the-race-days-stuttgart.org",
    request_endpoint="/wp-json/mo/v1/participants_table",
    headers=None,
    payload=" ",
) -> List[Dict[str, str]]:

    if headers is None:
        headers = {}

    conn = http.client.HTTPSConnection(url)
    conn.request("GET", request_endpoint, payload, headers)
    res = conn.getresponse()
    data = json.loads(res.read())

    df = pd.DataFrame(data)

    df["rating_category"] = df["fahrer_kategorie"].replace(
        rider_category2rating_category
    )

    return df
