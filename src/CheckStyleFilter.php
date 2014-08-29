<?php

class CheckStyleFilter {
    /**
     * @var SimpleXMLElement
     */
    protected $report;

    /**
     * @var FileLineFilterInterface
     */
    protected $filter;

    protected $removeQueue = [];

    public function __construct(SimpleXMLElement $report)
    {
        $this->report = $report;
    }

    public function asXML()
    {
        return $this->report->asXML();
    }

    /**
     * Determine the relative path for a given path
     *
     * @todo Extract elsewhere. This has nothing to do with Checkstyle
     * @param        $target
     * @param string $base_path
     *
     * @return string
     */
    public static function relpath($target, $base_path = '')
    {
        return '.' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR,
            array_diff_assoc(
                explode(DIRECTORY_SEPARATOR, $target),
                explode(DIRECTORY_SEPARATOR, $base_path)
            )
        );
    }

    public function addFilter(FileLineFilterInterface $filter)
    {
        $this->filter = $filter;
    }

    protected function includeFile(SimpleXMLElement $file)
    {
        return $this->filter->acceptFile((string) $file['name']);
    }

    protected function includeError(SimpleXMLElement $error, SimpleXMLElement $file)
    {
        return $this->filter->acceptLine((string) $file['name'], (int) $error['line']);
    }

    protected function queueRemoval(SimpleXMLElement $node)
    {
        $this->removeQueue[] = $node;
    }

    protected function doRemoval()
    {
        foreach ($this->removeQueue as $node) {
            $dom = dom_import_simplexml($node);
            $dom->parentNode->removeChild($dom);
        }
    }

    public function filter()
    {
        // Loop through each file
        foreach ($this->report as $file) {
            /* @var $file SimpleXMLElement */
            if (!$this->includeFile($file)) {
                $this->queueRemoval($file);
                continue;
            }

            // Loop through each error
            $errors = count($file->error);
            foreach ($file->error as $error) {
                /* @var $error SimpleXMLElement */
                if (!$this->includeError($error, $file)) {
                    $this->queueRemoval($error);
                    $errors--;
                    continue;
                }
            }

            // Check if file has no problems and remove it, if needed
            if (!$errors) {
                $this->queueRemoval($file);
            }
        }

        $this->doRemoval();
    }
}