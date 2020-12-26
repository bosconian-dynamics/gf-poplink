# Gravity Forms Population Links AddOn
![Build WordPress Plugin](https://github.com/bosconian-dynamics/gf-poplink/workflows/Build%20WordPress%20Plugin/badge.svg)

A GravityForms AddOn adding strategies and options for pre-populating form fields by links and tokens.

The latest feature release is available for download from [the Releases page](https://github.com/bosconian-dynamics/gf-poplink/releases). A build reflecting more recent states of the repository are available for download as a ["Build WordPress Plugin" workflow](https://github.com/bosconian-dynamics/gf-poplink/actions?query=workflow%3A%22Build+WordPress+Plugin%22) artifact attached to successful runs.

### Features
 - Pre-populate forms with stateless JWT tokens appended to a URL
 - Disable field inputs when pre-populated by a token
 - Pre-populate forms in-place on the frontend or use a packaged dedicated template

## Quick Start
 - Install and activate the plugin
 - Enable and configure Population Links in form settings
 - Enable "Allow field to be populated dynamically" in the Advanced settings for individual fields
 - Visit any form on the front-end while logged in and click the "Generate Population Link" button to create a link with a token to prefill that form