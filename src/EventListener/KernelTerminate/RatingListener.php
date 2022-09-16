<?php

/**
 * Proven Expert Ratings bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   Commercial
 * @copyright Copyright (c) 2022, numero2 - Agentur für digitales Marketing GbR
 */


namespace numero2\ProvenExpertRatingsBundle\EventListener\KernelTerminate;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\PageModel;
use Contao\FilesModel;
use Contao\System;
use Contao\Template;
use DateInterval;
use Exception;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Contao\CoreBundle\Routing\ResponseContext\JsonLd\JsonLdManager;
use Spatie\SchemaOrg\Graph;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Symfony\Component\HttpFoundation\RequestStack;
use Contao\CoreBundle\Asset\ContaoContext;


class RatingListener {


    /**
     * @var Symfony\Component\HttpFoundation\RequestStack
     */
    private $requestStack;

    /**
     * @var Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor
     */
    private $responseContextAccessor;

    /**
     * @var Contao\CoreBundle\Asset\ContaoContext
     */
    private $filesContext;

    /**
     * @var Symfony\Contracts\HttpClient\HttpClientInterface
     */
    private $client;


    public function __construct( RequestStack $requestStack, ResponseContextAccessor $responseContextAccessor, ContaoContext $filesContext, HttpClientInterface $client ) {

        $this->requestStack = $requestStack;
        $this->responseContextAccessor = $responseContextAccessor;
        $this->filesContext = $filesContext;
        $this->client = $client;
    }


    /**
     * Update the cache for the rating
     *
     * @param Symfony\Component\HttpKernel\Event\TerminateEvent $event
     */
    public function onKernelTerminate( TerminateEvent $event ) {

        $request = $event->getRequest();

        $page = $request->attributes->get('pageModel');
        if( !$page ) {
            return;
        }

        $root = PageModel::findOneById($page->trail[0]);

        if( !$root || empty($root->proven_expert_rating_api_id) || empty($root->proven_expert_rating_api_key) ) {
            return;
        }

        $scriptVersion = '1.8';
        $apiId = $root->proven_expert_rating_api_id;
        $apiKey = $root->proven_expert_rating_api_key;

        // cache
        $cache = new FilesystemAdapter();
        $cachedData = $cache->getItem('contao_proven_expert_rating:'.$root->id);

        // mark the cache to be updated
        if( !$cachedData->isHit() ) {

            $apiUrl = 'https://www.provenexpert.com/api_rating_v2.json';
            $url = $apiUrl . '?v=' . $scriptversion;

            $oOptions = new HttpOptions();
            $oOptions->setHeaders([
                'Authorization' => 'Basic '.base64_encode($apiId . ':' . $apiKey),
            ]);

            $aOptions = [];
            $aOptions = $oOptions->toArray();

            try {

                $response = null;
                $response = $this->client->request("GET", $url, $aOptions);

                $data = $response->getContent(false);
                $json = json_decode($data, true);

                if( $json['status'] == 'success' ) {
                    $result = [
                        'ratingValue' => $json['ratingValue'],
                        'reviewCount' => $json['reviewCount'],
                    ];

                    $cachedData->set($result);
                    $cachedData->expiresAfter(DateInterval::createFromDateString('1 hour'));
                    $cache->save($cachedData);

                    System::log('Updated Proven Expert rating for root id '. $root->id, __METHOD__, TL_CRON);
                }
            } catch( Exception $e ) {

                System::log('Updating Proven Expert rating for root id '. $root->id .' failed with: '. $e->getMessage(), __METHOD__, TL_CRON);
            }
        }
    }


    /**
     * add the rating data to the json ld
     *
     * @Hook("generatePage")
     */
    public function addJsonLdData() {

        $responseContext = $this->responseContextAccessor->getResponseContext();

        if( !$responseContext || !$responseContext->has(JsonLdManager::class) ) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $attributes = $this->requestStack->getCurrentRequest()->attributes;

        if( !$attributes->has('pageModel') ) {
            return;
        }

        $page = $attributes->get('pageModel');
        if( !$page ) {
            return;
        }

        $root = PageModel::findOneById($page->trail[0]);

        if( !$root || empty($root->proven_expert_rating_api_id) || empty($root->proven_expert_rating_api_key) ) {
            return;
        }

        $cache = new FilesystemAdapter();
        $cachedData = $cache->getItem('contao_proven_expert_rating:'.$root->id);

        // mark the cache to be updated
        if( !$cachedData->isHit() ) {
            return;
        }

        $image = FilesModel::findById($root->proven_expert_rating_image);
        $host = ($request->isSecure() ? 'https://' : 'http://') . $request->server->get('HTTP_HOST') . '/';
        if( $this->filesContext->getStaticUrl() ) {
            $host = $this->filesContext->getStaticUrl();
        }
        $imagePath = $host . $image->path;
        $rating = $cachedData->get();

        $jsonLd = [
            "@context" => "https://schema.org/",
            "@type" => "Product",
            "name" => $root->proven_expert_rating_name,
            "description" => $root->proven_expert_rating_description,
            "image" => [$imagePath],
            "brand" => [
                "@type" => "Brand",
            ],
            "review" => [
                "@type" => "Review",
                "author" => [
                    "@type" => "Person",
                    "name" => "Anonym",
                ],
            ],
            "aggregateRating" => [
                "@type" => "AggregateRating",
                "ratingValue" => $rating['ratingValue'] ?? '',
                "bestRating" => "5",
                "reviewCount" => $rating['reviewCount'] ?? '',
            ],
        ];

        /** @var JsonLdManager $jsonLdManager */
        $jsonLdManager = $responseContext->get(JsonLdManager::class);
        $type = $jsonLdManager->createSchemaOrgTypeFromArray($jsonLd);

        $jsonLdManager
            ->getGraphForSchema(JsonLdManager::SCHEMA_ORG)
            ->set($type, $jsonLd['identifier'] ?? Graph::IDENTIFIER_DEFAULT)
        ;
    }
}
