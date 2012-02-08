#!/bin/sh

cd web/js

find . -name '*.js*' | (JS='closure '; while read x; do JS="$JS --js $x"; done; bash -c "$JS")
