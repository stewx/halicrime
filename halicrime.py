'''
Stewart Rand 2015
Halifax crime tweets

Download Halifax crime stats and tweet interesting things

Pre-reqs:
sudo pip install twython
'''

import urllib
import datetime
import MySQLdb
import twython
import _mysql_exceptions
import os.path
import locale
import os
import sys
import logging
import filecmp
import shutil
import ConfigParser

CURRENT_DIR = os.path.dirname(os.path.realpath(__file__))

logging.basicConfig(filename='%s/logs/halicrime.log' % CURRENT_DIR, 
                    format='%(asctime)s %(levelname)s: %(message)s', level=logging.WARNING)
locale.setlocale(locale.LC_ALL, 'en_US.UTF-8')

config = ConfigParser.ConfigParser()
config.read('%s/settings.cfg' % CURRENT_DIR)

CSV_URL = config.get('main', 'data_url')

TWITTER_CONSUMER_KEY = config.get('twitter', 'consumer_key')
TWITTER_CONSUMER_SECRET = config.get('twitter', 'consumer_secret')
TWITTER_ACCESS_KEY = config.get('twitter', 'access_key')
TWITTER_ACCESS_SECRET = config.get('twitter', 'access_secret')
RATE_LIMIT = config.get('twitter', 'rate_limit')

DB_USERNAME = config.get('db', 'db_user')
DB_PASSWORD = config.get('db', 'db_pass')
DB_NAME = config.get('db', 'db_name')



def get_twitter_api():
    '''Get connection to Twitter API'''
    return twython.Twython(TWITTER_CONSUMER_KEY, TWITTER_CONSUMER_SECRET,
                           TWITTER_ACCESS_KEY, TWITTER_ACCESS_SECRET)


def get_db_connection():
    '''Get MySQL DB connection '''
    connection = MySQLdb.connect('localhost', DB_USERNAME, DB_PASSWORD, DB_NAME)
    connection.autocommit(True)
    return connection.cursor()


def parse_row(row):
    '''Get list of column values from row of CSV'''
    return row.strip().split(',')


def get_message_format():
    '''Get the longest message format that we can fit within the allotted characters
  
    '''
    message_format = ''
    return message_format


def load_data():
    '''Download latest CSV file and load any new data into DB'''
    db = get_db_connection()

    # Delete old event file and move current file to prev_events.csv
    latest = "%s/csv/latest_events.csv" % CURRENT_DIR
    prev = "%s/csv/prev_events.csv" % CURRENT_DIR
    if os.path.isfile(prev):
        os.remove(prev)
    if os.path.isfile(latest):
        shutil.move(latest, prev)

    # Download CSV
    print 'Downloading latest CSV.'
    urllib.urlretrieve(CSV_URL, latest)

    # Check if new file is same as old
    if os.path.isfile(prev) and filecmp.cmp(latest, prev):
        print 'No new data in CSV.'
        return

    # If it has new data, load data into DB
    with open(latest, 'rb') as csv_file:
        # Skip header line
        next(csv_file)
        # Parse each row
        for row in csv_file:
            print 'Examining row:\n%s' % ''.join(row)
            try:
                extracted_values = parse_row(row)
                if len(extracted_values) != 9:
                    raise ValueError('Expected 9 columns of data and got %i.' % len(extracted_values))
                longitude, latitude, _, _, event_id, date_string, street_name, event_type_id, event_type_name = extracted_values
                event_date = datetime.datetime.strptime(date_string[:10], '%Y-%m-%d').date()
            except ValueError:
                print 'Something wrong with this row: %s' % ''.join(row)
                continue

            # Check if it's new
            db.execute("""
                       SELECT *
                       FROM `events`
                       WHERE
                        `date` = "%s"
                        AND `event_id` = %s
                        AND `event_type_id` = %s
                       """ % (event_date, event_id, event_type_id))
            if db.rowcount != 0:
                print 'Already in DB.'
                continue
            else:
                print 'New entry.'

            # Insert into DB
            insert_query = """
                           INSERT INTO
                            `events` (`latitude`, `longitude`, `event_id`, `date`, `street_name`, `event_type_id`, `event_type`)
                           VALUES (%s, %s, %s, "%s", "%s", %s, "%s")
                           """ % (latitude, longitude, event_id, event_date, street_name,
                                  event_type_id, event_type_name)
            db.execute(insert_query)
    db.close()


def post_tweets():
    '''Tweet events from DB'''
    # Init connections
    db_connection = MySQLdb.connect('localhost', DB_USERNAME, DB_PASSWORD, DB_NAME)
    db_connection.autocommit(True)
    db_dict_cursor = db_connection.cursor(MySQLdb.cursors.DictCursor)

    api = get_twitter_api()

    # Get shortened URL length from Twitter API
    try:
        shortened_url_length = api.get_twitter_configuration()['short_url_length']
        print 'Short URL length as per Twitter API is %i.' % shortened_url_length
    except KeyError:
        shortened_url_length = 25

    tweet_count = 0

    # TODO: Figure out what to tweet
    
    db_connection.close()


def main():
    '''Execute main program'''

    if len(sys.argv) != 2 or sys.argv[1] not in ['load_data', 'tweet']:
        print 'Please supply a function: either load_data or tweet'
        exit()

    function = sys.argv[1]

    if function == 'load_data':
        load_data()
    elif function == 'tweet':
        post_tweets()

if __name__ == "__main__":
    main()
