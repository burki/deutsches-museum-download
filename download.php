<?php

/*
 * Deutschen Museum Digital publishes over 40'000 digitized books.
 * For every container there is a machine-readable METS/MODS file
 * that contains, among other information, pointer to the page scans
 * accessible through a IIIF-Server, see
 * https://blog.deutsches-museum.de/2022/12/16/ueber-40-000-buecher-im-deutschen-museum-digital
 *
 * This script takes a URL to such a container ($URL_METS) and downloads
 * every page as JPEG in the best resolution provided.
 *
 * Run
 *  composer install
 * to install required dependency ("symfony/dom-crawler": "^5.4")
 *
 * Set $URL_METS, then run
 *  php download.php
 *
 * Copyright 2024 Daniel Burckhardt
 * MIT License: https://opensource.org/license/mit
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the “Software”), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge,
 * publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall
 * be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 */
// we can't use EasyRdf / ML\JsonLD due to https://github.com/lanthaler/JsonLD/issues/75
require 'vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;

$URL_METS = 'https://digital.deutsches-museum.de/xml/metsmods/DMM_057003551532.xml';
// $URL_METS = 'https://digital.deutsches-museum.de/xml/metsmods/DMM_057003551598.xml';

function fetch_contents($url) {
    return file_get_contents($url);
}

function fetch_store($url, $fname) {
    $data = file_get_contents($url);
    if (!is_null($data)) {
        return file_put_contents($fname, $data);
    }

    echo "Could not fetch $url" . "\n";

    return false;
}

// https://iiif.io/api/image/3.0/#45-format
$MIME_MAP = [
    'image/jpeg' => 'jpg',
    'image/tiff' => 'tif',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/jp2' => 'jp2',
    'application/pdf' => 'pdf',
    'image/webp' => 'webp',
];

$mets = fetch_contents($URL_METS);

$crawler = new Crawler($mets);
$crawler->registerNamespace('mets', 'http://www.loc.gov/METS/');

foreach ($crawler->filterXPath('//mets:fileGrp[@USE="MAX"]//mets:file/mets:FLocat') as $node) {
    $href = $node->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
    $url_iiifinfo = $href . '/info.json';

    $info =  json_decode(fetch_contents($url_iiifinfo), true);

    $extension = '.jpg'; // TODO: switch to tif if preferred and contained in extraFormats

    $fname = basename($info['id']) . $extension;
    if (file_exists($fname)) {
        continue; // don't refetch
    }

    // build full-size image url
    $image_url = $info['id'] . '/full/max/0/default' . $extension;

    $res = fetch_store($image_url, $fname);
}
