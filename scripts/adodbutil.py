"""
ADOdb release management scripts utilities and helper classes.

- Environment class
  Reads configuration variables from the environment file, and makes them
  available in the 'env' global variable..
- Gitter class
  Use Gitter REST API to post announcements

This file is part of ADOdb, a Database Abstraction Layer library for PHP.

@package ADOdb
@link https://adodb.org Project's web site and documentation
@link https://github.com/ADOdb/ADOdb Source code and issue tracker

The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
and the GNU Lesser General Public Licence (LGPL) v2.1 or, at your option,
any later version. This means you can use it in proprietary products.
See the LICENSE.md file distributed with this source code for details.
@license BSD-3-Clause
@license LGPL-2.1-or-later

@copyright 2022 Damien Regad, Mark Newnham and the ADOdb community
@author Damien Regad
"""

from os import path

import requests
import yaml


class Environment:
    # See env.yml.sample for details about these config variables
    sf_api_key = None
    gitter_token = None
    gitter_room = None

    twitter_account = 'ADOdb_announce'
    twitter_api_key = None
    twitter_api_secret = None
    twitter_bearer_token = None  # Currently unused
    twitter_access_token = None
    twitter_access_secret = None

    def __init__(self, filename='env.yml'):
        """
        Constructor - load the config file and initialize properties.

        :param filename: Name of YAML config file to load
        """
        env_file = path.join(path.dirname(path.abspath(__file__)), filename)

        # Read the config file
        try:
            with open(env_file, 'r') as stream:
                config = yaml.safe_load(stream)
        except yaml.parser.ParserError as e:
            raise Exception("Invalid Environment file") from e

        # Assign class properties from config
        for key, value in config.items():
            setattr(self, key, value)


class Gitter:
    base_url = 'https://api.gitter.im/v1/'

    _headers = ''
    _room_id = ''

    def __init__(self, token, room_name):
        """
        Class Constructor.

        :param token: Gitter REST API token (see https://developer.gitter.im/apps)
        :param room_name: Room name, e.g. `ADOdb/ADOdb`
        """
        self._headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': 'Bearer ' + token.strip()
        }

        # Initialize Room Id
        r = requests.get(self.url('rooms'),
                         headers=self._headers,
                         params={'q': room_name})
        if r.status_code != requests.codes.ok:
            raise Exception(r.text)

        for room in r.json()['results']:
            if room['name'] == room_name:
                self._room_id = room['id']
        if not self._room_id:
            raise Exception("Gitter Room '{}' not found".format(room_name))

    def url(self, endpoint):
        """
        Get Gitter REST API URL for the given endpoint.

        :param endpoint: REST API endpoint
        :return: URL
        """
        return self.base_url + endpoint

    def post(self, message):
        """
        Post a message to a Gitter room.

        :param message: Message text

        :return: Posted message's Id
        """
        url = self.url('rooms/{}/chatMessages'.format(self._room_id))
        r = requests.post(url,
                          headers=self._headers,
                          json={'text': message})
        if r.status_code != requests.codes.ok:
            raise Exception(r.text)
        return r.json()['id']


# Initialize environment
env = Environment()
