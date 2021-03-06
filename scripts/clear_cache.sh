#!/bin/sh

LIB_DIRECTORY=/var/lib/nextdom

# Clear traductions cache
if [ -d ${LIB_DIRECTORY}/cache/i18n ]; then
    rm -fr ${LIB_DIRECTORY}/cache/i18n/*
fi

# Clear routes cache
if [ -d ${LIB_DIRECTORY}/cache/routes ]; then
    rm -fr ${LIB_DIRECTORY}/cache/routes/*
fi

# Clear twig cache
if [ -d ${LIB_DIRECTORY}/cache/twig ]; then
    rm -fr ${LIB_DIRECTORY}/cache/twig/*
fi
