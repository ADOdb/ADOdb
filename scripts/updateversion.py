#!/usr/bin/env -S python3 -u
"""
ADOdb version update script.

Updates the version number, and release date in all php and html files

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

@copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
@author Damien Regad
"""

from datetime import date
import getopt
import os
from os import path
import re
import subprocess
import sys

# ADOdb version validation regex
# These are used by sed - they are not PCRE !
_version_dev = "dev"
_version_abrc = r"(alpha|beta|rc)(\.([0-9]+))?"
_version_prerelease = r"(-?({0}|{1}))?".format(_version_dev, _version_abrc)
_version_base = r"[Vv]?([0-9]\.[0-9]+)(\.([0-9]+))?"
_version_regex = _version_base + _version_prerelease
_release_date_regex = r"(Unreleased|[0-9?]+-.*-[0-9]+)"
_changelog_file = "docs/changelog.md"

_tag_prefix = "v"

# Command-line options
options = "hct"
long_options = ["help", "commit", "tag"]


def usage():
    """
    Print script's command-line arguments help.
    """
    print('''Usage: {0} version

    Parameters:
        version                 ADOdb version, format: [v]X.YY[a-z|dev]

    Options:
        -c | --commit           Automatically commit the changes
        -t | --tag              Create a tag for the new release
        -h | --help             Show this usage message
'''.format(
        path.basename(__file__)
    ))
# end usage()


def version_is_dev(version):
    """
    Return true if version is a development release.
    """
    return version.endswith(_version_dev)


def version_is_prerelease(version):
    """
    Return true if version is alpha, beta or release-candidate.
    """
    return re.search(_version_abrc, version) is not None


def version_is_patch(version):
    """
    Return true if version is a patch release (i.e. X.Y.Z with Z > 0).
    """
    return (re.search('^' + _version_base + '$', version) is not None
            and not version.endswith('.0'))


def version_parse(version):
    """
    Breakdown the version into groups (Z and -dev are optional).

    Groups:
      - 1:(X.Y)
      - 2:(.Z)
      - 3:(Z)
      - 4:(-dev or -alpha/beta/rc.N)
      - 8: N
    """
    return re.match(r'^{0}$'.format(_version_regex), version)


def version_check(version):
    """
    Check that the given version is valid, exit with error if not.

    Returns the SemVer-normalized version without the "v" prefix
    - add '.0' if missing patch bit
    - add '-' before dev release suffix if needed
    """
    vparse = version_parse(version)
    if not vparse:
        usage()
        print("ERROR: invalid version ! \n")
        sys.exit(1)

    vnorm = vparse.group(1)

    # Add .patch version component
    if vparse.group(2):
        vnorm += vparse.group(2)
    else:
        # None was specified, assume a .0 release
        vnorm += '.0'

    # Normalize version number
    if version_is_dev(version):
        vnorm += '-' + _version_dev
    elif version_is_prerelease(version):
        vnorm += '-' + vparse.group(5)
        # If no alpha/beta/rc version number specified, assume 1
        if not vparse.group(8):
            vnorm += ".1"

    return vnorm


def get_release_date(version):
    """
    Return the release date in YYYY-MM-DD format, or 'Unreleased' for
    development releases.
    """
    # Development release
    if version_is_dev(version):
        return "Unreleased"
    else:
        return date.today().strftime("%Y-%m-%d")


def git_root():
    """
    Return the git repository's root (top-level) directory.
    """
    return subprocess.check_output(
        'git rev-parse --show-toplevel',
        text=True,
        shell=True
        ).rstrip()


def sed_script(version):
    """
    Build sed script to update version information in source files.
    """
    # Version number and release date
    script = r"/ADODB_vers/s/{0}(\s+{1})?/v{2}  {3}/".format(
        _version_regex,
        _release_date_regex,
        version,
        get_release_date(version)
    )
    return script


def sed_run(script, files):
    """
    Run sed.
    """
    subprocess.call(
        "sed -r -i '{0}' {1} ".format(script, files),
        shell=True
    )


def tag_name(version):
    """
    Return tag name (vX.Y.Z)
    """
    return _tag_prefix + version


def tag_check(version):
    """
    Checks if the tag for the specified version exists in the repository
    by attempting to check it out, throws exception if not.
    """
    subprocess.check_call(
        "git checkout --quiet " + tag_name(version),
        stderr=subprocess.PIPE,
        shell=True)
    print("Tag '{0}' already exists".format(tag_name(version)))


def tag_delete(version):
    """
    Deletes the specified tag
    """
    subprocess.check_call(
        "git tag --delete " + tag_name(version),
        stderr=subprocess.PIPE,
        shell=True)


def tag_create(version):
    """
    Create the tag for the specified version.

    Returns True if tag created.
    """
    print("Creating release tag '{0}'".format(tag_name(version)))
    result = subprocess.call(
        "git tag --sign --message '{0}' {1}".format(
            "ADOdb version {0} released {1}".format(
                version,
                get_release_date(version)
            ),
            tag_name(version)
        ),
        shell=True
    )
    return result == 0


def section_exists(filename, version, print_message=True):
    """
    Check given file for existing section with specified version.
    """
    for i, line in enumerate(open(filename)):
        if re.search(r'^## \[?' + version + r']', line):
            if print_message:
                print("  Existing section for v{0} found,"
                      .format(version), end=" ")
            return True
    return False


class UnsupportedPreviousVersion(Exception):
    pass


class NoPreviousVersion(Exception):
    pass


def version_get_previous(version):
    """
    Returns the previous version number.

    In pre-release scenarios, it would be complex to figure out what the
    previous version is, so it is not worth the effort to implement as
    this is a rare usage scenario; we just raise an exception in this case.
    - 'UnsupportedPreviousVersion' when attempting facing pre-release
      scenarios (rc -> beta -> alpha)
    - 'NoPreviousVersion' when processing major version or .1 pre-releases
      (can't handle e.g. alpha.0)
    """
    vprev = version.split('.')
    item = len(vprev) - 1

    while item > 0:
        try:
            val = int(vprev[item])
        except ValueError:
            raise UnsupportedPreviousVersion(
                "Retrieving pre-release's previous version is not supported")
        if val > 0:
            vprev[item] = str(val - 1)
            break
        item -= 1

    # Unhandled scenarios:
    # - major version number (item == 0)
    # - .0 pre-release
    if (item == 0
            or version_is_prerelease(version) and vprev[item] == '0'):
        raise NoPreviousVersion

    return '.'.join(vprev)


def update_changelog(version):
    """
    Update the release date in the Change Log.
    """
    print("Updating Changelog")

    # Version number without '-dev' suffix
    vparse = version_parse(version)
    version_release = vparse.group(1) + vparse.group(2)

    # Make sure previous version exists in changelog, ignore .0 pre-releases
    try:
        if version_is_dev(version):
            version_previous = version_get_previous(version_release)
        else:
            version_previous = version_get_previous(version)
        if not section_exists(_changelog_file, version_previous, False):
            raise ValueError(
                "ERROR: previous version {0} does not exist in changelog"
                .format(version_previous)
                )
    except NoPreviousVersion:
        if version_is_prerelease(version):
            version_previous = version_release
        else:
            version_previous = False

    # Remove patch component from previous version (x.y.z -> x.y)
    version_nopatch = version_parse(version_previous).group(1)

    # If version exists, update the release date
    if section_exists(_changelog_file, version):
        print('updating release date')
        script = r"s/^## \[{0}] .*$/## [{1}] - {2}/".format(
            version.replace('.', r'\.'),
            version,
            get_release_date(version)
            )
        # Set version link's target to release tag
        script += r";/^\[{0}\]/s/(\.\.\.).*$/\1v{0}/".format(
            version_release
            )

    else:
        # If it's a .0 release, treat it as dev
        if (not version_is_patch(version)
                and not version_is_prerelease(version)
                and not version_is_dev(version)):
            version += '-' + _version_dev

        # If development release already exists, nothing to do
        if (version_is_dev(version)
                and section_exists(_changelog_file, version_release)):
            print("nothing to do")
            return

        print("  Inserting new section for v{0}".format(version))

        # Prerelease section is inserted after the main version's,
        # otherwise we insert the new section before it.
        section_template = r"## \[{0}] - {1}"
        if version_is_prerelease(version):
            version_section = section_template.format(
                version,
                get_release_date(version)
                )
            version_section = "\\0\\n\\n" + version_section
        else:
            version_section = section_template.format(
                version_release,
                get_release_date(version)
                )
            version_section += "\\n\\n\\0"

        if version_previous:
            # Insert new section
            script = r"1,/^## \[({0}|{2})/s/^## \[({0}|{2}).*$/{1}/".format(
                version_nopatch,
                version_section,
                version_release
                )

            # Version number link target
            link_head = "v" + version
            if version_is_patch(version_release):
                link_search = version_previous
                if version_is_dev(version):
                    link_head = r"hotfix\/" + version_nopatch
            else:
                if version_is_prerelease(version):
                    link_search = version_release
                else:
                    link_search = version_nopatch
                if version_is_dev(version):
                    link_head = r"master\n"
            link_target = r"[{0}]: {1}v{2}...{3}".format(
                version_release if not version_is_prerelease(version) else version,
                "https://github.com/adodb/adodb/compare/".replace('/', r'\/'),
                version_previous,
                link_head
                )
            script += r";0,/^\[{0}/s//{1}\n&/".format(link_search, link_target)

        # We don't have a previous version, insert before the first section
        else:
            print("No previous version")
            script = "1,/^## /s/^## .*$/{0}/".format(version_section)

    sed_run(script, _changelog_file)

    print("  WARNING: review '{0}' to ensure added section is correct".format(
        _changelog_file
        ))

# end update_changelog


def version_set(version, do_commit=True, do_tag=True):
    """
    Bump version number and set release date in source files.
    """
    print("Preparing version bump commit")

    update_changelog(version)

    print("Updating version and date in source files")
    sed_run(sed_script(version), "adodb.inc.php")
    print("Version set to {0}".format(version))

    if do_commit:
        # Commit changes
        print("Committing")
        commit_ok = subprocess.call(
            "git commit --all --message '{0}'".format(
                "Bump version to {0}".format(version)
            ),
            shell=True
        )

        if do_tag:
            tag_ok = tag_create(version)
        else:
            tag_ok = False

        if commit_ok == 0:
            print('''
NOTE: you should carefully review the new commit, making sure updates
to the files are correct and no additional changes are required.
If everything is fine, then the commit can be pushed upstream;
otherwise:
 - Make the required corrections
 - Amend the commit ('git commit --all --amend' ) or create a new one''')

            if tag_ok:
                print(''' - Drop the tag ('git tag --delete {0}')
 - run this script again
'''.format(tag_name(version)))

    else:
        print("Note: changes have been staged but not committed.")
# end version_set()


def main():
    # Get command-line options
    try:
        opts, args = getopt.gnu_getopt(sys.argv[1:], options, long_options)
    except getopt.GetoptError as err:
        print(str(err))
        usage()
        sys.exit(2)

    if len(args) < 1:
        usage()
        print("ERROR: please specify the version")
        sys.exit(1)

    do_commit = False
    do_tag = False

    for opt, val in opts:
        if opt in ("-h", "--help"):
            usage()
            sys.exit(0)

        elif opt in ("-c", "--commit"):
            do_commit = True

        elif opt in ("-t", "--tag"):
            do_tag = True

    # Mandatory parameters
    version = version_check(args[0])

    # Change to Git repo's root directory
    os.chdir(git_root())

    # Let's do it
    version_set(version, do_commit, do_tag)
# end main()


if sys.version_info < (3, 7):
    print("ERROR: Python 3.7 or later is required")
    sys.exit(1)
if __name__ == "__main__":
    main()
