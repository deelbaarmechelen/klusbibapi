<?php

/*
 * This file is part of the Slim Image Resize middleware
 *
 * Copyright (c) 2014 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Project home:
 *   https://github.com/tuupola/slim-image-resize
 *
 */

namespace Api\Middleware\ImageResize;

class DefaultMutator extends MutatorAbstract
{
    protected static $regexp = "/(?<original>.+)-(?<size>(?<width>\d*)x(?<height>\d*))-?(?<signature>[0-9a-z]*)/";

    public function execute()
    {
        $width = null;
        $height = null;
        /* Fit or resize. */
        extract($this->options); // converts keys from options array into variables
        if (null !== $width && null !== $height) {
            $this->image->fit($width, $height);
        } else {
            $this->image->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
            });
        }

        return $this;
    }
}
