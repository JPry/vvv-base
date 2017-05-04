#!/usr/bin/env bash

# Change to the parent directory to run scripts.
cd "${VM_DIR}"

# Ensure composer is installed
noroot composer install

noroot php provision/init.php \
--vvv_path_to_site="${VVV_PATH_TO_SITE}" \
--vvv_config="${VVV_CONFIG}" \
--site="${SITE}" \
--site_escaped="${SITE_ESCAPED}" \
--vm_dir="${VM_DIR}"

cd -
