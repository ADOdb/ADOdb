#!/usr/bin/python -u
'''
    ADOdb release upload script
'''

import getopt
import glob
import os
from os import path
import subprocess
import sys


# ADOdb Repository reference
origin_repo = "git://git.code.sf.net/p/adodb/git adodb-git"
release_branch = "master"
release_prefix = "adodb"

# Directories and files to exclude from release tarballs
sf_dir = "{usr}@frs.sourceforge.net:" \
         "/home/frs/project/adodb/adodb-php5-only/adodb-{ver}-for-php5/"
rsync_cmd = 'rsync -vP --rsh ssh {src} ' + sf_dir

# Command-line options
options = "h"
long_options = ["help"]


def usage():
    print '''Usage: %s [options] username [release_path]

    This script will upload the files in the given directory (or the
    current one if unspecified) to Sourceforge.

    Parameters:
        username                Sourceforge user account
        release_path            Location of the release files to upload
                                (see buildrelease.py)

    Options:
        -h | --help             Show this usage message
''' % (
        path.basename(__file__)
    )
#end usage()


def main():
    # Get command-line options
    try:
        opts, args = getopt.gnu_getopt(sys.argv[1:], options, long_options)
    except getopt.GetoptError, err:
        print str(err)
        usage()
        sys.exit(2)

    if len(args) < 1:
        usage()
        print "ERROR: please specify the Sourceforge user and release_path"
        sys.exit(1)

    for opt, val in opts:
        if opt in ("-h", "--help"):
            usage()
            sys.exit(0)

    # Mandatory parameters
    username = args[0]

    try:
        release_path = args[1]
        os.chdir(release_path)
    except IndexError:
        release_path = os.getcwd()

    # Get the version number from the zip file to upload
    try:
        zipfile = glob.glob('*.zip')[0]
    except IndexError:
        print "ERROR: release zip file not found in '%s'    " % release_path
        sys.exit(1)
    version = zipfile[5:8]

    # Upload files to Sourceforge with rsync
    print "Uploading ADOdb release files to Sourceforge..."
    print
    subprocess.call(
        rsync_cmd.format(
            usr=username,
            src=path.join(release_path, "*"),
            ver=version
        ),
        shell=True
    )

#end main()

if __name__ == "__main__":
    main()
