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
import re
import urllib.parse
from os import path

import requests
import yaml
from markdown import markdown


class Environment:
    # See env.yml.sample for details about these config variables
    sf_api_key = None

    github_token = None
    github_repo = 'ADOdb/ADOdb'

    gitter_token = None
    gitter_room = 'ADOdb/ADOdb'

    matrix_token = None
    matrix_domain = 'gitter.im'
    matrix_room = '#ADOdb_ADOdb:' + matrix_domain

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


class Matrix:
    """
    Posting messages to a Matrix room via REST API
    """
    api_root = '_matrix/client/v3/'
    domain = ''
    base_url = ''
    room_alias = ''
    room_id = ''

    _headers = ''

    def __init__(self, domain, token, room_alias):
        """
        Class Constructor.

        :param domain: Matrix Server's domain name
        :param token: Matrix REST API token
        :param room_alias: Matrix Room alias, e.g. `#room:server.id`
        """
        self._headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': 'Bearer ' + token.strip()
        }

        self.domain = domain
        self._set_base_url()
        self._set_room(room_alias)


    def url(self, endpoint):
        """
        Get Matrix REST API URL for the given endpoint.

        :param endpoint: REST API endpoint
        :return: URL
        """
        return self.base_url + endpoint

    def _set_base_url(self):
        """
        Retrieve the Matrix API base URL for the given Domain and initialize
        the self.base_url property.
        """
        r = requests.get(f'https://{self.domain}/.well-known/matrix/client')

        if r.status_code != requests.codes.ok:
            raise Exception(r.text)

        self.base_url = r.json()['m.homeserver']['base_url'] + \
                        '/' + self.api_root

    def _set_room(self, alias):
        """
        Retrieve the Matrix Room ID from the given alias (after adding the
        leading '#' and the Server name if not provided) and initialize the
        room_alias and room_id properties.

        :param alias:
        """
        if not alias:
            raise Exception("Matrix Room Alias not defined")

        # Add leading '#' if needed
        if alias[0] != '#':
            alias = '#' + alias
        # If the alias does not include the Server, add the domain
        if ':' not in alias:
            alias += ':' + self.domain
        self.room_alias = alias

        # Retrieve Room ID
        url = self.url('directory/room/') + urllib.parse.quote(alias)
        r = requests.get(url, headers=self._headers)
        if r.status_code != requests.codes.ok:
            raise Exception(r.json()['error'])

        self.room_id = r.json()['room_id']

    def post(self, message):
        """
        Post a message to a Matrix room.

        :param message: Message text in Markdown format

        :return: Posted message's ID
        """
        html = markdown(message)
        plain_text = re.sub(r'(<!--.*?-->|<[^>]*>)', '', html)
        payload = {
            'msgtype': 'm.text',
            'body': plain_text,
            'format': 'org.matrix.custom.html',
            'formatted_body': html,
        }

        url = self.url(f'rooms/{self.room_id}/send/m.room.message')
        r = requests.post(url, headers=self._headers, json=payload)
        if r.status_code != requests.codes.ok:
            raise Exception(r.text)

        return r.json()['event_id']


# Initialize environment
env = Environment()
