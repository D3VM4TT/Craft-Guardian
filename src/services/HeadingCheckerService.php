<?php

namespace boost\craftguardian\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use GuzzleHttp\Exception\RequestException;

class HeadingCheckerService extends Component
{
    public function checkAll(): array
    {
        $results = [];

        $entries = Entry::find()
            ->site('*')
            ->status(null)
            ->drafts(false)
            ->provisionalDrafts(false)
            ->revisions(false)
            ->all();

        $client = Craft::createGuzzleClient([
            'timeout' => 10,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);

        foreach ($entries as $entry) {
            $url = $entry->getUrl();

            if (!$url) {
                continue;
            }

            try {
                $response = $client->request('GET', $url);
                $html = (string)$response->getBody();
                $issues = $this->analyzeHeadingStructure($html);

                if (!empty($issues)) {
                    $results[] = [
                        'entry' => $entry,
                        'url' => $url,
                        'issues' => $issues,
                    ];
                }
            } catch (RequestException $e) {
                $results[] = [
                    'entry' => $entry,
                    'url' => $url,
                    'issues' => ['Error fetching page: ' . $e->getMessage()],
                ];
            }
        }

        return $results;
    }

    private function analyzeHeadingStructure(string $html): array
    {
        $issues = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $headings = [];
        for ($i = 1; $i <= 6; $i++) {
            foreach ($xpath->query("//h{$i}") as $node) {
                $headings[] = ['level' => $i, 'text' => trim($node->textContent)];
            }
        }

        $h1s = array_filter($headings, fn($h) => $h['level'] === 1);
        if (count($h1s) === 0) {
            $issues[] = 'Missing <h1> tag';
        } elseif (count($h1s) > 1) {
            $issues[] = 'Multiple <h1> tags';
        }

        $prevLevel = 0;
        foreach ($headings as $heading) {
            if ($prevLevel > 0 && $heading['level'] > $prevLevel + 1) {
                $issues[] = "Heading level jumps from <h{$prevLevel}> to <h{$heading['level']}>";
            }
            $prevLevel = $heading['level'];
        }

        return $issues;
    }
}
