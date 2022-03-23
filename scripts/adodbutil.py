"""
ADOdb release management scripts utilities and helper classes.

- Environment class
  Reads configuration variables from the environment file, and makes them
  available in the 'env' global variable..

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
import yaml


class Environment:
    # See env.yml.sample for details about these config variables
    sf_api_key = None

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


# Initialize environment
env = Environment()
