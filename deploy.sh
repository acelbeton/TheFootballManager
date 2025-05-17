#!/bin/bash
echo "Starting deployment from subdirectory"
cd football-manager
composer install --no-dev
npm ci --audit false
npm run build