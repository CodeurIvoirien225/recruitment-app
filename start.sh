#!/bin/bash

# Mettre à jour pip, setuptools et wheel
python -m pip install --upgrade pip setuptools wheel

# Installer les dépendances
pip install -r requirements.txt
