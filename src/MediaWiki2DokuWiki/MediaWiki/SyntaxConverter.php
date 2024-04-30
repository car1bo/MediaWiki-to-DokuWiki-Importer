<?php
/**
 * MediaWiki2DokuWiki importer.
 * Copyright (C) 2011-2013  Andrei Nicholson
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   MediaWiki2DokuWiki
 * @author    Andrei Nicholson
 * @copyright Copyright (C) 2011-2013 Andrei Nicholson
 * @link      https://github.com/tetsuo13/MediaWiki-to-DokuWiki-Importer
 */

/**
 * Convert syntaxes.
 *
 * Regular expressions originally by Johannes Buchner
 * <buchner.johannes [at] gmx.at>.
 *
 * Changes by Frederik Tilkin:
 *
 * <ul>
 * <li>uses sed instead of perl</li>
 * <li>resolved some bugs ('''''IMPORTANT!!!''''' becomes //**IMPORTANT!!!** //,
 *     // becomes <nowiki>//</nowiki> if it is not in a CODE block)</li>
 * <li>added functionality (multiple lines starting with a space become CODE
 *     blocks)</li>
 * </ul>
 *
 * @author Andrei Nicholson
 * @author Johannes Buchner
 * @author Frederik Tilkin
 * @since  2012-05-07
 */
class MediaWiki2DokuWiki_MediaWiki_SyntaxConverter
{
    /** Original MediaWiki record. */
    private $record = '';

    /** Stored code blocks to prevent further conversions. */
    private $codeBlock = array();

    /** What string should never occur in user content? */
    private $placeholder = '';

    /**
     * Constructor.
     *
     * @param string $record MediaWiki record.
     */
    public function __construct($record)
    {
        $this->placeholder = '@@' . __CLASS__ . '_';
        $this->record = $record;
    }

    /**
     * Convert page syntax from MediaWiki to DokuWiki.
     *
     * @return string DokuWiki page.
     * @author Johannes Buchner <buchner.johannes [at] gmx.at>
     * @author Frederik Tilkin
     */
    public function convert()
    {
        $recordtmp = $this->convertCodeBlocks($this->record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }

        //$record = $this->convertHeadings($record);
        $recordtmp = $this->convertList($record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }

        $recordtmp = $this->convertUrlText($record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }

        $recordtmp = $this->convertLink($record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }

        $recordtmp = $this->convertDoubleSlash($record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }

        $recordtmp = $this->convertBoldItalic($record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }

        $recordtmp = $this->convertTalks($record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }
        
        $recordtmp = $this->convertHorizontalLines($record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }

        $recordtmp = $this->convertHtmlEntities($record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }

        $recordtmp = $this->convertTables($record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }

        $recordtmp = $this->convertOtherTags($record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }
        
        $recordtmp = $this->convertImagesFiles($record);
        if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }

        if (count($this->codeBlock) > 0) {
            $recordtmp = $this->replaceStoredCodeBlocks($record);
            if(!($recordtmp === null || trim($recordtmp) === '')) { $record = $recordtmp; }
        }

        return $record;
    }

    /**
     * Double forward slashes are not italic. There is no double slash syntax
     * rule in MediaWiki. This conversion must happen before the conversion of
     * italic markup.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertDoubleSlash($record)
    {
        $patterns = array(
            '/([^:])\/\//m' => '\1<nowiki>//</nowiki>',
        );
        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }

    private function convertCodeBlocks($record)
    {
        $patterns = array(
            // Change the ones that have been replaced in a link [] BACK to
            // normal (do it twice in case
            // [http://addres.com http://address.com] ) [quick and dirty]
            '/([\[][^\[]*)(<nowiki>)(\/\/+)(<\/nowiki>)([^\]]*)/' => '\1\3\5',
            '/([\[][^\[]*)(<nowiki>)(\/\/+)(<\/nowiki>)([^\]]*)/' => '\1\3\5',

            '@</code>\n[ \t]*\n<code>@' => ''
        );

        $result = preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );

        $pattern = array(
            '@<pre>(.*?)?</pre>@s',
            '@<nowiki>(.*?)?</nowiki>@s'
        );

        $result = preg_replace_callback(
            //'@<pre>(.*?)?</pre>@s',
            $pattern,
            function ($code)
            {
                array_push($this->codeBlock, $code[1]);
                $replace = $this->placeholder . (count($this->codeBlock) - 1) . '@@';                
                $convertedLine ='<code>' . $replace . '</code>';
                return $convertedLine;
            },
            $result
        );

        return $result;
    }

    /**
     * Replace PRE tag placeholders back with their original content.
     *
     * @param string $record Converted record.
     *
     * @return string Record with placeholders removed.
     */
    private function replaceStoredCodeBlocks($record)
    {
        for ($i = 0; $i < count($this->codeBlock); $i++) {
            
            $placeholderValue = $this->placeholder . $i . '@@';
            $record = str_replace(
                $placeholderValue,
                $this->codeBlock[$i],
                $record
            );
        }
        return $record;
    }

    /**
     * Convert images and files.
     *
     * @param string $record Converted record.
     *
     * @return string
     */
    private function convertImagesFiles($record)
    {
        $numMatches = preg_match_all(
            '/\[\[(Image|File):(.*?)\]\]/',
            $record,
            $matches
        );

        if ($numMatches === 0 || $numMatches === false) {
            return $record;
        }

        for ($i = 0; $i < $numMatches; $i++) {
            $converted = $this->convertImage($matches[2][$i]);

            // Replace the full tag, [[File:example.jpg|options|caption]],
            // with the DokuWiki equivalent.
            $record = str_replace($matches[0][$i], $converted, $record);
        }

        return $record;
    }

    /**
     * Process a MediaWiki image tag.
     *
     * @param string $detail Filename and options, ie.
     *                       example.jpg|options|caption.
     *
     * @return string DokuWiki version of tag.
     */
    private function convertImage($detail)
    {
        $parts = explode('|', $detail);
        $numParts = count($parts);

        // Image link.
        if ($numParts == 2 && substr($parts[1], 0, 5) == 'link=') {
            return '[[' . substr($parts[1], 5) . '|{{wiki:' . $parts[0] . '}}]]';
        }

        $converted = '{{';
        $leftAlign = '';
        $rightAlign = '';
        $imageSize = '';
        $caption = '';

        if ($numParts > 1) {
            $imageFilename = array_shift($parts);

            foreach ($parts as $part) {
                if ($part == 'left') {
                    $leftAlign = ' ';
                    continue;
                } else if ($part == 'right') {
                    $rightAlign = ' ';
                    continue;
                } else if ($part == 'center') {
                    $leftAlign = $rightAlign = ' ';
                    continue;
                }

                if (substr($part, -2) == 'px') {
                    preg_match('/((\d+)x)?(\d+)px/', $part, $matches);

                    if (count($matches) > 0) {
                        if ($matches[1] == '') {
                            $imageSize = $matches[3];
                        } else {
                            $imageSize = $matches[2] . 'x' . $matches[3];
                        }
                    }

                    continue;
                }

                $caption = $part;
            }

            $converted .= $leftAlign . 'wiki:' . $imageFilename . $rightAlign;

            if ($imageSize != '') {
                $converted .= '?' . $imageSize;
            }

            if ($caption != '') {
                $converted .= '|' . $caption;
            }
        } else {
            $converted .= "wiki:$detail";
        }

        $converted .= '}}';

        return $converted;
    }

    /**
     * Convert talks.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertTalks($record)
    {
        $patterns = array(
            '/^[ ]*:/'  => '>',
            '/>:/'      => '>>',
            '/>>:/'     => '>>>',
            '/>>>:/'    => '>>>>',
            '/>>>>:/'   => '>>>>>',
            '/>>>>>:/'  => '>>>>>>',
            '/>>>>>>:/' => '>>>>>>>'
        );

        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }

    /**
     * Convert bold and italic.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertBoldItalic($record)
    {
        $patterns = array(
            "/'''''(.*)'''''/" => '//**\1**//',
            "/'''/"            => '**',
            "/''/"             => '//',

            // Changes by Reiner Rottmann: - fixed erroneous interpretation
            // of combined bold and italic text.
            '@\*\*//@'         => '//**'
        );

        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }

    /**
     * Convert [link] => [[link]].
     *
     * @param string $record
     *
     * @return string
     */
    private function convertLink($record)
    {
        $patterns = array('/([^[]|^)(\[[^]]*\])([^]]|$)/' => '\1[\2]\3');

        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }

    /**
     * Convert [url text] => [url|text].
     *
     * @param string $record
     *
     * @return string
     */
    private function convertUrlText($record)
    {
        $patterns = array(
            '/([^[]|^)(\[[^] ]*) ([^]]*\])([^]]|$)/' => '\1\2|\3\4'
        );

        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }

    /**
     * Convert lists.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertList($record)
    {
        $patterns = array(
            '/^\* /m'    => '  * ',
            '/^\*{2} /m' => '    * ',
            '/^\*{3} /m' => '      * ',
            '/^\*{4} /m' => '        * ',
            '/^# /m'     => '  - ',
            '/^#{2} /m'  => '    - ',
            '/^#{3} /m'  => '      - ',
            '/^#{4} /m'  => '        - '
        );

        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }

    /**
     * Convert headings. Syntax between MediaWiki and DokuWiki is completely
     * opposite: the largest heading in MediaWiki is two equal marks while in
     * DokuWiki it's six equal marks. This creates a problem since the first
     * replaced string of two marks will be caught by the last search string
     * also of two marks, resulting in eight total equal marks.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertHeadings($record)
    {
        $patterns = array(
            '/^======(.+)======\s*$/m' => '==\1==',
            '/^=====(.+)=====\s*$/m'   => '===\1===',
            '/^====(.+)====\s*$/m'     => '====\1====',
            '/^===(.+)===\s*$/m'       => '=====\1=====',
            '/^==(.+)==\s*$/m'         => '======\1======'
        );

        // Insert a unique string to the replacement so that it won't be
        // caught in a search later.
        array_walk(
            $patterns,
            function (&$item, $key) {
                $item = $this->placeholder . $item;
            }
        );

        $convertedRecord = preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );

        // No headings were found.
        if ($convertedRecord == $record) {
            return $record;
        }

        // Strip out the unique strings.
        return str_replace($this->placeholder, '', $convertedRecord);
    }

    /**
     * Convert html character entities.
     * See https://www.w3schools.com/html/html_entities.asp
     *
     * @param string $record
     *
     * @return string
     */
    private function convertHtmlEntities($record)
    {
        $patterns = array(
            '/&nbsp;/'   => ' ',
            '/&lt;/'     => '<',
            '/&gt;/'     => '>',
            '/&amp;/'    => '&',
            '/&quot;/'   => '"',
            '/&apos;/'   => '\'',
            '/&cent;/'   => '¢',
            '/&pound;/'  => '£',
            '/&yen;/'    => '¥',
            '/&euro;/'   => '€',
            '/&copy;/'   => '©',
            '/&reg/'     => '®'
        );

        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }

    /**
     * Convert horizontal lines.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertHorizontalLines($record)
    {
        $patterns = array(
            '/^=+\s*\**<br>\**\s*=+/'   => '----'
        );

        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }

    /**
     * Convert other tags.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertOtherTags($record)
    {
        $patterns = array(
            '/<br>/'        => "\r\n",
            '/<br \/>/'     => "\r\n",
            '/<br\/>/'      => "\r\n",
            '/<nowiki>/'    => '<code>',
            '/<\/nowiki>/'  => '</code>',
            '/<pre>/'       => '<code>',
            '/<\/pre>/'     => '</code>'
        );

        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }
 
    private function convertTables($record)
    {
        return preg_replace_callback(
            '/(\{\|.*\n((.*|\n)*?)\|\})/',
            function ($matches) {

                $data = $matches[1];
                /*
                MediaWiki2DokuWiki_Environment::out(
                    PHP_EOL . '=====matched===== ' . $data,
                    false
                );
                */
                # https://www.mediawiki.org/wiki/Help:Tables
                # {|	table start, required
                # |+	table caption, optional; only between table start and table row
                # |-	table row, optional on first row—wiki engine assumes the first row
                # !	    table header cell, optional. Consecutive table header cells may be added on same line separated by double marks (!!) or start on new lines, each with its own single mark (!).
                # |	    table data cell, optional. Consecutive table data cells may be added on same line separated by double marks (||) or start on new lines, each with its own single mark (|).
                # |}	table end, required

                $section = "tableStart";
                $result = "";
                $constructedLineString = "";
                $lines = preg_split('/\r\n|\r|\n/', $data);
                $headerPresent = false;
                $columnIndex = 0; # applies to data rows only
                $rowIndex = 0; # applies to data rows only
                foreach ($lines as $line) {           
                                
                    # Determine the type of line we're processing
                    $tableStart = preg_match('/^\{\|/', $line);
                    $caption = preg_match('/^\|\+/', $line);
                    $tableRow = preg_match('/^\|\-/', $line);
                    $headerCell = preg_match('/^\!/', $line);
                    $tableEnd = preg_match('/^\|\}/', $line);
                    $dataCell = (preg_match('/^\|/', $line) && !($tableStart || $tableEnd || $caption || $tableRow || $headerCell));
                    $dataRow = !($tableStart || $tableEnd || $caption || $tableRow || $headerCell);
                    $rowDelimiter = $tableRow || $tableEnd;
                    /*
                    MediaWiki2DokuWiki_Environment::out(
                        PHP_EOL . 'processing line ' . $line,
                        false
                    );
                    MediaWiki2DokuWiki_Environment::out(
                        PHP_EOL . '    tableStart-' . $tableStart . ' caption-' . $caption . ' tableRow-' . $tableRow . ' headerCell-' . $headerCell . ' tableEnd-' . $tableEnd . ' dataCell-' . $dataCell,
                        false
                    );
                    */
                    # Start of table
                    if ($tableStart) {
                        # Nested tables not supported, abort!
                        if ($section != "tableStart") { return $data; }
                        /*
                        MediaWiki2DokuWiki_Environment::out(
                            PHP_EOL . '    Detected Table Start' . $line,
                            false
                        );
                        */
                        # Disregard the table start, it has nothing useful for dokuwiki, so move to next phase.
                        $section = "expectHeader";
                        continue;
                    }

                    # Captions are unsupported in Dokuwiki, ignore if found
                    if ($caption) {
                        /*
                        MediaWiki2DokuWiki_Environment::out(
                            PHP_EOL . '    Detected caption' . $line,
                            false
                        );
                        */
                        continue;
                    }

                    # Headers are optional, so check if they are present before processing
                    if ($section == "expectHeader") {
                        if ($headerCell) {
                            $section = "header";
                            /*
                            MediaWiki2DokuWiki_Environment::out(
                                PHP_EOL . '    found header start at line ' . $line,
                                false
                            );
                            */
                        } elseif ($tableEnd) {
                            # If we see a table end straight after a table start, ignore the table entirely as it is empty.
                            return $result; 
                        } elseif ($tableRow) {
                            # If we see a row marker |- directly after a table start, ignore it
                            continue; 
                        } else {
                            $section = "rows";
                            /*
                            MediaWiki2DokuWiki_Environment::out(
                                PHP_EOL . '    No header start found, starting row processing',
                                false
                            );
                            */
                        }
                    }

                    if ($section == "header") {
                        if ($tableRow) {
                            /*
                            MediaWiki2DokuWiki_Environment::out(
                                PHP_EOL . '    found row delimiter, string output- ' . $constructedLineString,
                                false
                            );
                            */

                            // clean data lines
                            $patterns = array(
                                '/<br>/'        => '\\\\ ',
                                '/<br \/>/'     => "\\\\",
                                '/<br\/>/'      => "\\\\",
                                '/<strike>/'    => '<del>',
                                '/<\/strike>/'  => '</del>',
                                '/<b>/'         => '**',
				                '/<\/b>/'       => '**',
                                '/<u>/'         => '',
                                '/<\/u>/'       => ''
                            );
                    
                            $constructedLineString = preg_replace(
                                array_keys($patterns),
                                array_values($patterns),
                                $constructedLineString
                            );

                            # A table row token |- marks the end of header section,
                            # so add the header line to the result with the trailing column separator
                            $result = $result . "^ " . $constructedLineString . PHP_EOL;
                            $constructedLineString = "";
                            $section = "rows";
                            continue;
                        }
                        elseif ($headerCell) {
                            # A header row can contain multiple cells, so remove the header token ! and split on the header delimiter !! first
                            $headerLine = ltrim($line, "!");
                            $headerCells = explode("!!", $headerLine);

                            # Now join them back up with the Dokuwiki header separator ^
                            foreach ($headerCells as $Cell) {
                                $constructedLineString = $constructedLineString . $Cell . " ^ ";
                            }
                        } elseif ($tableEnd) {
                            # If we see a table end within a header, ignore the table entirely as it is empty.
                            return $result; 
                        }
                    }

                    if ($section == "rows") {
                        if ($rowDelimiter) {
                            /*
                            MediaWiki2DokuWiki_Environment::out(
                                PHP_EOL . '    found row delimiter, string output- ' . $constructedLineString,
                                false
                            );
                            */
                            // clean data lines
                            $patterns = array(
                                '/<br>/'        => '\\\\ ',
                                '/<br \/>/'     => "\\\\",
                                '/<br\/>/'      => "\\\\",
                                '/<strike>/'    => '<del>',
                                '/<\/strike>/'  => '</del>',
                                '/<b>/'         => '**',
				                '/<\/b>/'       => '**',
                                '/<u>/'         => '',
                                '/<\/u>/'       => ''
                            );
                    
                            $constructedLineString = preg_replace(
                                array_keys($patterns),
                                array_values($patterns),
                                $constructedLineString
                            );
                            
                            # A table row token |- marks the end of a row,
                            # and a table end token |} marks the end of the table.
                            # Add the row to the result with the trailing column separator
                            $result = $result . $constructedLineString . "| " . PHP_EOL;
                            $constructedLineString = "";
                        }
                        elseif ($dataRow) {
                            /*
                            MediaWiki2DokuWiki_Environment::out(
                                PHP_EOL . '    found data row ' . $line,
                                false
                            );
                            */
                            # A data row can contain multiple cells, so remove the row token | and split on the row delimiter || first
                            $continuation = ltrim($line, "|") == $line;
                            $dataLine = ltrim($line, "|");
                            $dataCells = explode("||", $dataLine);

                            # Now join them back up with the Dokuwiki row separator |
                            $separator = $continuation ? "" : "| ";
                            foreach ($dataCells as $Cell) {
                                $constructedLineString = $constructedLineString . $separator . $Cell . " " ;
                                $separator = "| ";
                            }
                        } 
                    }
                }
                /*
                MediaWiki2DokuWiki_Environment::out(
                    PHP_EOL . '--Converted Table-- ' . PHP_EOL . $result . PHP_EOL,
                    false
                );
                */
                return $result;
            },
            $record
        );
    }
}

