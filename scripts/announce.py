#!/usr/bin/env -S python3 -u
"""
ADOdb announcements script.

Posts release announcements to Gitter

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

from adodbutil import env, Gitter


def main():
    message = """ADOdb Version {0} release
See changelog https://github.com/ADOdb/ADOdb/blob/v{0}/docs/changelog.md
""".format('5.21.3')

    gitter = Gitter(env.gitter_token, env.gitter_room)
    message_id = gitter.post('# ' + message)
    print("Message posted successfully\n"
          "https://gitter.im/{}?at={}"
          .format(env.gitter_room, message_id))


if __name__ == "__main__":
    main()
