#!/usr/bin/env bash

# Change to the parent directory to run scripts.
cd "${VM_DIR}"

# Install composer with --no-dev if this is a repo, otherwise use the regular install.
if [[ false != "${REPO}" ]]; then
    echo "Running composer with --no-dev"
    noroot composer install --no-dev --no-suggest --no-interaction
else
    echo "Running composer"
    noroot composer install --no-interaction
fi

noroot php provision/init.php \
--vvv_path_to_site="${VVV_PATH_TO_SITE}" \
--vvv_config="${VVV_CONFIG}" \
--site="${SITE}" \
--site_escaped="${SITE_ESCAPED}" \
--vm_dir="${VM_DIR}"

cd -
