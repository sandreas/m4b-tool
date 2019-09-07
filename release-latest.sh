#!/usr/bin/env bash
# https://stackoverflow.com/questions/8044583/how-can-i-move-a-tag-on-a-git-branch-to-a-different-commit
# https://gist.github.com/stefanbuck/ce788fee19ab6eb0b4447a85fc99f447

git push origin :refs/tags/latest && \
    git tag -fa -m 'updated latest tag' latest && \
    git push origin master --tags && \
    ./build




