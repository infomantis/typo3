<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Seo\Tests\Functional\XmlSitemap;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Frontend\Tests\Functional\SiteHandling\AbstractTestCase;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Contains functional tests for the XmlSitemap Index
 */
class XmlSitemapRecordsTest extends AbstractTestCase
{
    /**
     * @var string[]
     */
    protected $coreExtensionsToLoad = [
        'core',
        'frontend',
        'seo'
    ];

    protected function setUp()
    {
        parent::setUp();
        $this->importDataSet('EXT:seo/Tests/Functional/Fixtures/pages-sitemap.xml');
        $this->importDataSet('EXT:seo/Tests/Functional/Fixtures/sys_category.xml');
        $this->importDataSet('EXT:seo/Tests/Functional/Fixtures/tt_content.xml');
        $this->setUpFrontendRootPage(
            1,
            [
                'constants' => ['EXT:seo/Configuration/TypoScript/XmlSitemap/constants.typoscript'],
                'setup' => [
                    'EXT:seo/Configuration/TypoScript/XmlSitemap/setup.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/records.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/content.typoscript'
                ],
            ]
        );

        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1, 'http://localhost/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/'),
                $this->buildLanguageConfiguration('FR', '/fr'),
            ]
        );
    }

    /**
     * @test
     * @dataProvider sitemapEntriesToCheck
     */
    public function checkIfSiteMapIndexContainsSysCategoryLinks($host, $expectedEntries, $notExpectedEntries): void
    {
        $response = $this->executeFrontendRequest(
            (new InternalRequest($host))->withQueryParameters(
                [
                    'type' => 1533906435,
                    'sitemap' => 'records',
                ]
            )
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('Content-Length', $response->getHeaders());
        $stream = $response->getBody();
        $stream->rewind();
        $content = $stream->getContents();

        foreach ($expectedEntries as $expectedEntry) {
            self::assertContains($expectedEntry, $content);
        }

        foreach ($notExpectedEntries as $notExpectedEntry) {
            self::assertNotContains($notExpectedEntry, $content);
        }

        $this->assertGreaterThan(0, $response->getHeader('Content-Length')[0]);
    }

    /**
     * @return array
     */
    public function sitemapEntriesToCheck(): array
    {
        return [
            'default-language' => [
                'http://localhost/',
                [
                    'http://localhost/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=2&amp;',
                    '<priority>0.5</priority>'
                ],
                [
                    'http://localhost/?tx_example_category%5Bid%5D=3&amp;',
                    'http://localhost/fr/?tx_example_category%5Bid%5D=3&amp;',
                ]
            ],
            'french-language' => [
                'http://localhost/fr',
                [
                    'http://localhost/fr/?tx_example_category%5Bid%5D=3&amp;',
                    '<priority>0.5</priority>'
                ],
                [
                    'http://localhost/fr/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/fr/?tx_example_category%5Bid%5D=2&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=2&amp;',
                ]
            ],
        ];
    }

    /**
     * @test
     */
    public function checkIfSiteMapIndexContainsCustomChangeFreqAndPriorityValues(): void
    {
        $response = $this->executeFrontendRequest(
            (new InternalRequest('http://localhost/'))->withQueryParameters(
                [
                    'id' => 1,
                    'type' => 1533906435,
                    'sitemap' => 'content',
                ]
            )
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('Content-Length', $response->getHeaders());
        $stream = $response->getBody();
        $stream->rewind();
        $content = $stream->getContents();

        self::assertContains('<changefreq>hourly</changefreq>', $content);
        self::assertContains('<priority>0.7</priority>', $content);

        $this->assertGreaterThan(0, $response->getHeader('Content-Length')[0]);
    }
}