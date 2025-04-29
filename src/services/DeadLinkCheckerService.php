<?php

namespace boost\craftguardian\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use GuzzleHttp\Exception\RequestException;

class DeadLinkCheckerService extends Component
{
    private array $lastResults = [];

    /**
     * Scans all entries for dead links in their string-based field content.
     *
     * @return array<int, array{
     *     entry: \craft\elements\Entry,
     *     field: string,
     *     url: string,
     *     status: int|null,
     *     error: string|null
     * }>
     */
    public function scanAllEntries(): array
    {
        $brokenLinks = [];

        $entries = Entry::find()
            ->status(null)
            ->site('*')
            ->type('*')
            ->drafts(false)
            ->revisions(false)
            ->provisionalDrafts(false)
            ->all();

        foreach ($entries as $entry) {
            try {
                $layout = $entry->getFieldLayout();
            } catch (\Throwable $e) {
                Craft::warning("Skipping entry ID {$entry->id}: " . $e->getMessage(), __METHOD__);
                continue;
            }

            if (!$layout) {
                continue;
            }

            foreach ($layout->getCustomFields() as $field) {
                $value = $entry->getFieldValue($field->handle);

                if ($value instanceof \craft\elements\db\MatrixBlockQuery) {
                    foreach ($value->all() as $block) {
                        foreach ($block->getFieldLayout()->getCustomFields() as $subField) {
                            $subValue = $block->getFieldValue($subField->handle);

                            if ($subValue instanceof \craft\ckeditor\data\FieldData) {
                                $subValue = $subValue->getRawContent();
                            }

                            if (is_string($subValue) && (str_contains($subValue, 'http') || str_contains($subValue, '<a '))) {
                                $brokenLinks = array_merge(
                                    $brokenLinks,
                                    $this->scanStringForLinks(
                                        $subValue,
                                        $entry,
                                        $field->handle . ' → ' . $subField->handle
                                    )
                                );
                            }
                        }
                    }
                } elseif ($value instanceof \craft\ckeditor\data\FieldData) {
                    $rawContent = $value->getRawContent();

                    if (is_string($rawContent) && (str_contains($rawContent, 'http') || str_contains($rawContent, '<a '))) {
                        $brokenLinks = array_merge(
                            $brokenLinks,
                            $this->scanStringForLinks($rawContent, $entry, $field->handle)
                        );
                    }
                } elseif (is_string($value) && (str_contains($value, 'http') || str_contains($value, '<a '))) {
                    $brokenLinks = array_merge(
                        $brokenLinks,
                        $this->scanStringForLinks($value, $entry, $field->handle)
                    );
                }
            }
        }

        $this->lastResults = $brokenLinks;
        return $brokenLinks;
    }

    public function getLastResults(): array
    {
        return $this->lastResults;
    }

    /**
     * Parses content for links and checks if they are reachable.
     *
     * @param string $content
     * @param Entry $entry
     * @param string $fieldHandle
     * @return array<int, array{
     *     entry: Entry,
     *     field: string,
     *     url: string,
     *     status: int|null,
     *     error: string|null
     * }>
     */
    private function scanStringForLinks(string $content, Entry $entry, string $fieldHandle): array
    {
        $client = Craft::createGuzzleClient([
            'timeout' => 10,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);

        $results = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
        $links = $dom->getElementsByTagName('a');

        foreach ($links as $link) {
            $url = $link->getAttribute('href');

            if (!$url || str_starts_with($url, '#') || str_starts_with($url, 'mailto:')) {
                continue;
            }

            try {
                $fullUrl = $this->normalizeUrl($url);
                $response = $client->request('HEAD', $fullUrl);
                $status = $response->getStatusCode();

                if ($status >= 400) {
                    $results[] = [
                        'entry' => $entry,
                        'field' => $fieldHandle,
                        'url' => $url,
                        'status' => $status,
                        'error' => null,
                    ];
                }
            } catch (RequestException $e) {
                $results[] = [
                    'entry' => $entry,
                    'field' => $fieldHandle,
                    'url' => $url,
                    'status' => null,
                    'error' => $e->getMessage(),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'entry' => $entry,
                    'field' => $fieldHandle,
                    'url' => $url,
                    'status' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Normalizes a URL, assuming it may be relative.
     */
    private function normalizeUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim(Craft::$app->sites->primarySite->getBaseUrl(), '/') . '/' . ltrim($url, '/');
    }
}
