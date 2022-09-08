#!/usr/bin/env -S python3 -u
"""
ADOdb announcements script.

Posts release announcements to
- Gitter
- Twitter

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

import argparse
from datetime import date
import json
import re
from pathlib import Path

import tweepy  # https://www.tweepy.org/
from git import Repo  # https://gitpython.readthedocs.io
# https://github.com/PyGithub/PyGithub
from github import Github, GithubException, Milestone

from adodbutil import env, Gitter


def process_command_line():
    """
    Parse command-line options
    :return: Namespace
    """
    # Get most recent Git tag
    repo = Repo(path=Path(__file__).parents[1])
    tags = sorted(repo.tags, key=lambda t: t.tag.tagged_date)
    latest_tag = str(tags[-1])

    parser = argparse.ArgumentParser(
        description="Post ADOdb release announcement messages to Gitter."
    )
    parser.add_argument('version',
                        nargs='?',
                        default=latest_tag,
                        help="Version number to announce; if not specified, "
                             "the latest tag will be used.")
    parser.add_argument('-m', '--message',
                        help="Additional text to add to announcement message")
    parser.add_argument('-b', '--batch',
                        action="store_true",
                        help="Batch mode - do not ask for confirmation "
                             "before posting")

    only = parser.add_mutually_exclusive_group()
    only.add_argument('-g', '--gitter-only',
                      action="store_true",
                      help="Only post the announcement to Gitter")
    only.add_argument('-t', '--twitter-only',
                      action="store_true",
                      help="Only post the announcement to Twitter")
    only.add_argument('-u', '--github-only',
                      action="store_true",
                      help="Only post the announcement to GitHub")

    return parser.parse_args()


def github_close_milestone(repo, version):
    print(f"Closing Milestone '{version}'")

    # Search Milestone for version
    milestone_found = False
    milestone: Milestone.Milestone
    for milestone in repo.get_milestones():
        if milestone.title == version:
            milestone_found = True
            break

    # Milestone not found, check if already closed
    if not milestone_found:
        # Process closed Milestones in reverse order of due_on, to minimize
        # number of iterations
        for milestone in repo.get_milestones(state='closed',
                                             sort='due_on',
                                             direction='desc'):
            if milestone.title == version:
                print(f"Already closed {milestone.raw_data['html_url']}")
                return
        raise Exception(f"Milestone '{version}' not found")

    # Close the milestone
    # noinspection PyUnboundLocalVariable
    milestone.edit(title=milestone.title,
                   state='closed',
                   due_on=date.today())


def post_github(version, message, changelog_link):
    print(f"GitHub Release for repository '{env.github_repo}'")

    gh = Github(env.github_token)
    repo = gh.get_repo(env.github_repo)

    # Check if Release already exists
    version = 'v' + version
    try:
        rel = repo.get_release(version)
        print(f"Existing release '{version}' found", rel.html_url)

        # Discard the message provided on command-line, and use the one from
        # the Release's description, inform user to update it on GitHub.
        if message:
            print(f"Your message will be discarded; "
                  "the Release's description will be used instead.\n"
                  "Edit it on GitHub if needed")
        else:
            print("Retrieving the Release's description for the "
                  "announcement message")

        # Remove the changelog link to keep only the release's message
        message = re.sub(r"[,.]?\s*(Please )?See .*$",
                         "",
                         rel.body,
                         flags=re.IGNORECASE).strip()
        if message:
            message += ".\n"
    except GithubException as err:
        if err.status != 404:
            raise err
        print(f"Release '{version}' does not exist yet")

        # Make sure the version has been tagged
        try:
            repo.get_git_ref('tags/' + version)
            print(f"Tag '{version}' found")
        except GithubException:
            print(f"ERROR: Tag '{version}' not found")
            exit(1)

        # Create the release
        rel = repo.create_git_release(version,
                                      version,
                                      message + changelog_link)
        print("Release created successfully", rel.html_url)

    print()

    # Closing the Milestone
    try:
        github_close_milestone(repo, version)
    except Exception as e:
        print(str(e))
        exit(1)

    # Return message to be used for remaining announcements
    return message


def post_gitter(message):
    print("Posting to Gitter... ", end='')
    gitter = Gitter(env.gitter_token, env.gitter_room)
    message_id = gitter.post('# ' + message)
    print("Message posted successfully\n"
          "https://gitter.im/{}?at={}"
          .format(env.gitter_room, message_id))
    print()


def post_twitter(message):
    print("Posting to Twitter... ", end='')
    twitter = tweepy.Client(
        consumer_key=env.twitter_api_key,
        consumer_secret=env.twitter_api_secret,
        access_token=env.twitter_access_token,
        access_token_secret=env.twitter_access_secret
    )
    try:
        r = twitter.create_tweet(text=message)
    except tweepy.errors.HTTPException as e:
        err = json.loads(e.response.text)
        print("ERROR")
        print(e, "-", err['detail'])
        return
    print("Tweeted successfully\n"
          "https://twitter.com/{}/status/{}"
          .format(env.twitter_account, r.data['id']))
    print()


def main():
    args = process_command_line()

    post_everywhere = not args.gitter_only \
        and not args.github_only \
        and not args.twitter_only
    version = args.version.lstrip('v')
    changelog_url = f"https://github.com/ADOdb/ADOdb/blob/v{version}" \
                    "/docs/changelog.md"
    message = args.message.rstrip(".") + ".\n" if args.message else ""

    # Create GitHub release, retrieve message from it if it already exists
    if post_everywhere or args.github_only:
        message = post_github(version,
                              message,
                              f"See [Changelog]({changelog_url}) for details")
    if args.github_only:
        return

    # Build announcement message
    msg_announce = "ADOdb Version {0} released\n{1}{2}".format(
        version,
        message,
        "See Changelog " + changelog_url
    )

    # Get confirmation
    if not args.batch:
        print("Review ", end='')
    print("Announcement message")
    print("-" * 27)
    print(msg_announce)
    print("-" * 27)
    if not args.batch:
        reply = input("Proceed with posting ? ")
        if not reply.casefold() == 'y':
            print("Aborting")
            exit(1)
    print()

    if post_everywhere or args.gitter_only:
        post_gitter(msg_announce)
    if post_everywhere or args.twitter_only:
        post_twitter(msg_announce)


if __name__ == "__main__":
    main()
