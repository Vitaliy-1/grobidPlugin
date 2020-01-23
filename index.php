<?php

/**
 * @file plugins/generic/grobid/index.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University Library
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v2.
 *
 * @brief wrapper for the Grobid Plugin
 */

require_once('GrobidPlugin.inc.php');

return new GrobidPlugin();
