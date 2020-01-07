#!/usr/bin/env bash
set -e

CURRENT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"

source ${CURRENT_DIR}/scripts/utils.sh

#################################################################################################
################################### NextDom Installation from git ###############################
#################################################################################################

if [[! -f ${ROOT_DIRECTORY}/.git  ]] ; then
    addLogInfo "Il n'y a pas de dossier .git"
fi

sed -i "s|PRODUCTION=true|PRODUCTION=false|g" ${CURRENT_DIR}/scripts/config.sh

bash ${CURRENT_DIR}/scripts/preinst.sh
bash ${CURRENT_DIR}/scripts/postinst.sh


