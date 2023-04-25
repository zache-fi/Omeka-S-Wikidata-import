# Update old url to new url in Wikidata via editing whole wikidata item
#
## Running the code: Create virtual env, activate it, install pywikibot, json, requests libraries, create pywikibot config, run the code
#
# python -m venv ./venv
# source venv/bin/activate
# pip install pywikibot requests json
# "echo "usernames['wikidata']['wikidata'] = 'YOUR_WIKIDATA_USERNAME'" > user-config.py
# python update_links.py

old_url = "http://www.poriartmuseum.fi/arkistodata/Porin_kaupungin_taidekokoelma_2020-04-27.xlsx"
new_url = "https://docs.google.com/spreadsheets/d/1vdxkjH_7ejM2Lme4KFjDkfezb0Y9PlhMCoWVFbN1Q7I/edit#gid=499687690"

import requests
import pywikibot
import json

def update_reference_urls(wikidata_site, qid, old_url, new_url):
    updated = False
    old_url='http://' + old_url

    # Load wikidata item in JSON string format and replace links

    item = pywikibot.ItemPage(wikidata_site, qid)
    item.get()
    oldtext=json.dumps(item.toJSON(),indent=2)
    newtext=oldtext.replace(old_url, new_url)
    newjson=json.loads(newtext)

    # Show diff and ask confirmation        

    pywikibot.showDiff(oldtext, newtext)
    label = item.labels.get("fi")
    question=label + ' ( ' + qid +' )' + ' - Do you want to accept these changes?'
    choice = pywikibot.input_choice(
            question,
            [('Yes', 'Y'), ('No', 'n')],
            default='Y',
            automatic_quit=False
         )
    if choice.lower() == 'y':
       item.editEntity(newjson, summary="Updating reference links")
       updated = True

    return updated

def find_and_update_pages_linking_to_url(old_url, new_url):
    wikidata_site = pywikibot.Site("wikidata", "wikidata")

    # Find all pages with old_url

    base_url = f"https://wikidata.org/w/api.php"
    params = {
        "action": "query",
        "format": "json",
        "list": "exturlusage",
        "euquery": old_url,
        "euprop": "title",
        "eunamespace": 0,  # Change this to search in other namespaces
        "eulimit": 500,    # Maximum number of results per request
    }

    # Load only 500 url per round and iterate until there is no new urls
    while True:
        response = requests.get(base_url, params=params)
        data = response.json()

        if "query" in data:
            for link in data["query"]["exturlusage"]:

                # Do actual updating
                updated=update_reference_urls(wikidata_site, link["title"], old_url, new_url)
                if updated:
                   print(" updated\n")
                else:
                   print("\n")
                
        if "continue" in data:
            params["eucontinue"] = data["continue"]["eucontinue"]
        else:
            break

def main():
    # Set the URL you want to search for

    # Find pages linking to the target URL
    find_and_update_pages_linking_to_url(old_url.replace('http://',''), new_url)

if __name__ == "__main__":
    main()

