<?php
/*
    Ashor, a dead-simple blogging platform, version 0.4.
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

echo "Ashor by Jeff Meli, licensed under AGPLv3. Visit fej.io for info.\n";

require "parser.php";

/* 0x20 or decimal 32 is the US-ASCII value for the space character.
This is useful because characters with values 0 through 31 are control characters. */
const ORD_OF_SPACE_CHARACTER = 32; 

$files = scandir("posts");
if ($files === FALSE)
    die("Couldn't scan the posts folder.");

$indexNum = 0;

//Loop through every post.ashor file and spit out an HTML file.
foreach ($files as $file) {
    if (!strcasecmp(substr($file, strlen($file) - 6), ".ashor")) {
        $source = fopen("posts/$file", "r") or die("I couldn't open source file $file.");
        $text = fread($source, filesize("posts/$file"));
        fclose($source);

        $postText = $title = $dateStr = $dateFromSource = $postID = "";

        //Copy everything in the first line to the title string, excluding the \n at the end.
        for ($i = 0; $text[$i] != "\n"; $i++)
            if (ord($text[$i]) >= ORD_OF_SPACE_CHARACTER)
                $title .= $text[$i];
        $i++; //Increment to get to the next line (in other words, skip the \n)

        //Do the same as above for the date *string*.
        for (; $text[$i] != "\n"; $i++)
            if (ord($text[$i]) >= ORD_OF_SPACE_CHARACTER)
                $dateFromSource .= $text[$i];
        $i++;

        //Having the date as an object is useful for parsing it, both for the byline and for the <time> tag.
        $date = date_create($dateFromSource);
        $dateStr = "posted on the " . $date->format("jS") . " of " . $date->format("F") . ", " . $date->format("Y");
        if (!$date)
            die("I couldn't parse the date. Try fixing it.");

        //And finally for the post ID.
        for (; $text[$i] != "\n"; $i++)
            if (ord($text[$i]) >= ORD_OF_SPACE_CHARACTER)
                $postID .= $text[$i];
        $i++;

        $postText .= "<div id='ashor-title'>" . parse($title, $postID, TRUE)["html"] . "</div>\n";
        $postText .= "<div id='ashor-date'>" . parse($dateStr, $postID, TRUE)["html"] . "</div>\n";

        $parsed = parse($text, $postID, FALSE, $i);
        $postText .= $parsed["html"];
        $index[$indexNum++] = ["title" => $title, "dateStr" => $dateStr,
            "dateObj" => $date, "beforeTheFoldText" => $parsed["beforeTheFoldText"], "postID" => $postID];

        $postTemplateFile = fopen("templates/post-template.html", "r") or die("I couldn't read the post template.");
        $postTemplate = fread($postTemplateFile, filesize("templates/post-template.html"));
        fclose($postTemplateFile);
        $finalPost = str_replace('<!-- Ashor post -->', $postText, $postTemplate, $numOfReplacements);
        if (!$numOfReplacements)
            echo "I couldn't find the special token in the post template.";

        $html = fopen("blog/$postID.html", "w") or die("I couldn't write an HTML file.");
        fwrite($html, $finalPost);
        fclose($html);
    }
}

//The strcmp comparison is intentionally reversed to order posts by newest-to-oldest.
if (!usort($index, function ($post1, $post2) { return strcmp($post2["postID"], $post1["postID"]); }))
    die("Couldn't sort the posts, for some reason.");

$indexHTML = "<main id='ashor-index'>\n";
foreach ($index as $post) {
    $indexHTML .=
        "    <section class='ashor-index-post'>\n" .
        "        <div class='ashor-index-title'>{$post["title"]}</div>\n" .
        "        <time class='ashor-index-date' datetime='" . $post["dateObj"]->format("Y-m-d") . "'>" .
        "{$post["dateStr"]}</time>\n" .
        "        <div class='ashor-index-content'>{$post["beforeTheFoldText"]}</div>\n" .
        "        <a class='ashor-index-link' href='blog/{$post["postID"]}.html'>Continue reading »</a>\n" .
        "    </section>\n";
}
$indexHTML .= "</main>\n";

$indexTemplatePath = "templates/index-template.html";
$indexTemplateFile = fopen($indexTemplatePath, "r") or die("I couldn't read the post template.");
$indexTemplate = fread($indexTemplateFile, filesize($indexTemplatePath));
$indexReplaced = str_replace('<!-- Ashor index -->', $indexHTML, $indexTemplate, $numOfReplacements);
if ($numOfReplacements > 1)
    echo "[Warning] More than one index marker was found. Check your index template.";

$indexFile = fopen("blog-index.html", "w") or die("I couldn't write an HTML file. (2)");
fwrite($indexFile, $indexReplaced);
fclose($indexFile);

echo "...done.\n";
