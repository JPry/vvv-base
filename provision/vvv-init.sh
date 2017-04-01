#!/usr/bin/env bash

# Change to the parent directory to run scripts.
cd ..

# List all variables
set -o posix; set

# Run composer
noroot composer update
