# Installation
```bash
pip3 install -r requirements.txt
```

# Use the Skript
Make sure to have a valid Internet connection!
```bash
python3 main_ingest.py #to update all the items in the Database, deletes all old Results inside the Database
python3 make_resultsfiles.py # generate all  the resultsfiles from the state of the Database
```


## Add new Resultfiles
Add the path to the new file inside the ```config.yaml``` file like so:
Before:
```yaml
ResultFilesSetup:
  race_name2result_file:
    { "Plattenhardt": "../01_Data/ingest/Ergebnis 15.08.2021.xlsx" }
```

After:
```yaml
ResultFilesSetup:
  race_name2result_file:
    { 
     "Plattenhardt": "../01_Data/ingest/Ergebnis 15.08.2021.xlsx",
     "Vaihingen": "../01_Data/ingest/Ergebnis 16.08.2021.xlsx"  # new Resultfilepath
    }
```



