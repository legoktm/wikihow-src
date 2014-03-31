<?php
//
// Processes all the unproccessed entries from the slideshow_todo table
//

require_once('commandLine.inc');

GallerySlide::batchGenerateThumbs();
