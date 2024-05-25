<?php

namespace App\Message;

class ImageToProcess
{
    private $imageId;

    public function __construct(int $imageId)
    {
        $this->imageId = $imageId;
    }

    public function getContent(): int
    {
        return $this->imageId;
    }
}
