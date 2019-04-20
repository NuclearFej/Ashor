<?php
/*
    Ashor, a dead-simple blogging platform.
    Copyright © 2016 Jeff Meli. See https://fej.io for contact information.

    Ashor is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    Ashor is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with Ashor.  If not, see <http://www.gnu.org/licenses/>.
*/

abstract class Tag {
    const Bold = 1;
    const Italics = 2;
    const Header = 3;
    const BeforeTheFold = 4;
    const Picture = 5;
    const Link = 6;
}

/* Parses Ashor text and returns an array with these elements:
"html" => String of parsed HTML text.
"beforeTheFoldText" => The string of parsed HTML text to appear on the index page,
as designated by the \f tag, or the empty string if no \f tag is found.
    
Starts parsing the string $toParse from index $pos. */
function parse($text, $postID, $oneLine = FALSE, $pos = 0) {
    $newline = 0;
    $escaped = false;
    $tagStack[] = array();

    if (!$oneLine)
        $ret["html"] = "<p class='ashor-p'>";
    else
        $ret["html"] = "";
    $ret["beforeTheFoldText"] = "";

    for (; $pos < strlen($text); $pos++) {
        if ($text[$pos] == "\n")
            $newline++;
        elseif (ord($text[$pos]) >= ORD_OF_SPACE_CHARACTER) { //See ashor.php for this constant
            if ($newline == 1) {
                $ret["html"] .= "\n";
                $newline = 0;
            } elseif ($newline >= 2){
                $ret["html"] .= "</p>\n<p class=\"ashor-p\">";
                $newline = 0;
            }

            if (!$escaped) {
                if ($text[$pos] == "\\")
                    $escaped = true;
                else
                    $ret["html"] .= $text[$pos];
            } else { //If the next character is escaped, we parse the tag.
                if ($text[$pos] == "]") {
                    $closingTag = popTagStack($tagStack);
                    if ($closingTag != NULL)
                        $ret["html"] .= $closingTag;
                    else
                        echo "[Warning] Closed a tag where there was none.";
                } elseif ($text[$pos] == "b" || $text[$pos] == "B") {
                    $ret["html"] .= "<span class=\"ashor-bold\">";
                    $tagStack[] = Tag::Bold;
                } elseif ($text[$pos] == "i" || $text[$pos] == "I") {
                    $ret["html"] .= "<span class=\"ashor-italics\">";
                    $tagStack[] = Tag::Italics;
                } elseif ($text[$pos] == "h" || $text[$pos] == "H") {
                    $ret["html"] .= "<span class=\"ashor-header\">";
                    $tagStack[] = Tag::Header;
                } elseif ($text[$pos] == "p" || $text[$pos] == "P") { //'p' is for 'picture'
                    $innards = parseTagWithAttribute($text, ++$pos);
                    $ret["html"] .= "<img class='ashor-img' src='img/$postID/{$innards["attrib"]}'>";
                    $pos = $innards["pos"]; //$pos is the ending ']', which will then be immediately incremented.
                } elseif ($text[$pos] == "l" || $text[$pos] == "L") {
                    $innards = parseTagWithAttribute($text, ++$pos);
                    $ret["html"] .= "<a class='ashor-link' href='{$innards["attrib"]}'>";
                    $pos = $innards["pos"];
                    $tagStack[] = Tag::Link;
                } elseif ($text[$pos] == "f" || $text[$pos] == "F") //'f' is for 'fold'
                    $ret["beforeTheFoldText"] = closeBeforeTheFoldText($ret["html"], $tagStack) . "</p>";
                elseif ($text[$pos] == "r" || $text[$pos] == "R") //'r' is for 'return'
                    $ret["html"] .= "<br>";
                elseif ($text[$pos] == "\\")
                    $ret["html"] .= "\\";
                else
                    echo "This character was unduly escaped: " . $text[$pos];

                $escaped = false;
            }

        }
    }

    if (!$oneLine)
        $ret["html"] .= "</p>";
    return $ret;
}

// Returns the string between both brackets, as well as the index of the ending bracket.
function parseTagWithAttribute($str, $startPos) {
    if ($str[$startPos] != "[")
        die("There's a misformatted tag at location $startPos.");
    $ret["pos"] = strpos($str, "]", ++$startPos);
    if ($ret["pos"] === FALSE)
        die("Couldn't find a closing ']' for the tag around $startPos.");
    $ret["attrib"] = substr($str, $startPos, $ret["pos"] - $startPos);
    return $ret;
}

// Returns $beforeTheFoldText with all open tags closed.
function closeBeforeTheFoldText($beforeTheFoldText, $tagStack) {
    $tagStackLength = count($tagStack);
    for ($i = 0; $i < $tagStackLength; $i++)
        $beforeTheFoldText .= popTagStack($tagStack);
    return $beforeTheFoldText;
}

/* Pops the topmost element from the referenced tagStack and returns
its corresponding closing tag, or NULL if the stack is empty. */
function popTagStack(& $tagStack) {
    switch (array_pop($tagStack)) {
        case Tag::Bold:
        case Tag::Italics:
        case Tag::Header:
            return "</span>";
        case Tag::Link:
            return "</a>";
        default:
            return NULL;
    }
}
