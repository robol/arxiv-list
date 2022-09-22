#!/bin/bash

version=$(grep "^Version:" arxiv-list.php | cut -d ":" -f2 | xargs)

cd ..
zip -r arxiv-list-$version.zip arxiv-list/*.php arxiv-list/*.js arxiv-list/README.md
cd arxiv-list
