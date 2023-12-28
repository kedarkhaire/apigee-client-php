<?php

/*
 * Copyright 2024 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Apigee\Edge\Api\Monetization\Controller;

use Apigee\Edge\Api\Monetization\Entity\AcceptedRatePlanInterface;
use Apigee\Edge\Api\Monetization\Entity\RatePlanInterface;
use Apigee\Edge\Api\Monetization\Serializer\AcceptedRatePlanSerializer;
use Apigee\Edge\ClientInterface;
use Apigee\Edge\Controller\EntityListingControllerTrait;
use Apigee\Edge\Controller\EntityLoadOperationControllerTrait;
use Apigee\Edge\Serializer\EntitySerializerInterface;
use DateTimeImmutable;
use Psr\Http\Message\UriInterface;
use ReflectionClass;

abstract class EligibleRatePlanController extends OrganizationAwareEntityController implements EligibleRatePlanControllerInterface
{
    use EntityListingControllerTrait;
    use EntityLoadOperationControllerTrait;
    use PaginatedListingHelperTrait;

    /**
     * EligibleRatePlanController constructor.
     *
     * @param string $organization
     * @param ClientInterface $client
     * @param \Apigee\Edge\Serializer\EntitySerializerInterface|null $entitySerializer
     */
    public function __construct(string $organization, ClientInterface $client, ?EntitySerializerInterface $entitySerializer = null)
    {
        $entitySerializer = $entitySerializer ?? new AcceptedRatePlanSerializer();
        parent::__construct($organization, $client, $entitySerializer);
    }

    /**
     * {@inheritdoc}
     */
    public function getAllEligibleRatePlans(): array
    {
        return $this->getEligibleRatePlan();
    }

    /**
     * {@inheritdoc}
     */
    public function acceptRatePlan(RatePlanInterface $ratePlan, DateTimeImmutable $startDate, ?DateTimeImmutable $endDate = null, ?int $quotaTarget = null, ?bool $suppressWarning = null, ?bool $waveTerminationCharge = null): AcceptedRatePlanInterface
    {
        $rc = new ReflectionClass($this->getEntityClass());
        /** @var AcceptedRatePlanInterface $acceptedRatePlan */
        $acceptedRatePlan = $rc->newInstance(
            [
                'ratePlan' => $ratePlan,
                'startDate' => $startDate,
            ]
        );
        if (null !== $quotaTarget) {
            $acceptedRatePlan->setQuotaTarget($quotaTarget);
        }
        if (null !== $endDate) {
            $acceptedRatePlan->setEndDate($endDate);
        }
        $payload = $this->getEntitySerializer()->serialize($acceptedRatePlan, 'json', $this->buildContextForEntityTransformerInCreate());
        $tmp = json_decode($payload, true);
        if (null !== $suppressWarning) {
            $tmp['suppressWarning'] = $suppressWarning ? 'true' : 'false';
        }
        if (null !== $waveTerminationCharge) {
            $tmp['waveTerminationCharge'] = $waveTerminationCharge ? 'true' : 'false';
        }
        $payload = json_encode($tmp);
        $response = $this->client->post($this->getBaseEndpointUri(), $payload);
        $this->getEntitySerializer()->setPropertiesFromResponse($response, $acceptedRatePlan);

        return $acceptedRatePlan;
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-suppress PossiblyNullArgument - id is not null in this context.
     */
    public function updateSubscription(AcceptedRatePlanInterface $acceptedRatePlan, ?bool $suppressWarning = null, ?bool $waveTerminationCharge = null): void
    {
        $payload = $this->getEntitySerializer()->serialize($acceptedRatePlan, 'json', $this->buildContextForEntityTransformerInCreate());
        $tmp = json_decode($payload, true);
        if (null !== $suppressWarning) {
            $tmp['suppressWarning'] = $suppressWarning ? 'true' : 'false';
        }
        if (null !== $waveTerminationCharge) {
            $tmp['waveTerminationCharge'] = $waveTerminationCharge ? 'true' : 'false';
        }
        $this->alterRequestPayload($tmp, $acceptedRatePlan);
        $payload = json_encode($tmp);
        // Update an existing entity.
        $response = $this->client->put($this->getEntityEndpointUri($acceptedRatePlan->id()), $payload);
        $this->getEntitySerializer()->setPropertiesFromResponse($response, $acceptedRatePlan);
    }

    /**
     * Returns the URI for listing rate plans eligible to access.
     *
     * Gets the API products that a company is eligible to access, including:
     * API products for which a company has accepted a rate plan.
     * API products that do not have a published rate plan.
     *
     * @return UriInterface
     */
    abstract protected function getEligibleRatePlanEndpoint(): UriInterface;

    /**
     * Builds context for the entity normalizer.
     *
     * Allows controllers to add extra metadata to the payload.
     *
     * @return array
     */
    abstract protected function buildContextForEntityTransformerInCreate(): array;

    /**
     * Allows to alter payload before it gets sent to the API.
     *
     * @param array $payload
     *   API request payload.
     */
    protected function alterRequestPayload(array &$payload, AcceptedRatePlanInterface $acceptedRatePlan): void
    {
    }

    /**
     * Helper function for listing eligible rate plans.
     *
     * @param array $query_params
     *   Additional query parameters.
     *
     * @return \Apigee\Edge\Api\Monetization\Entity\AcceptedRatePlanInterface[]
     *
     * @psalm-suppress PossiblyNullArrayOffset - id() does not return null here.
     */
    private function getEligibleRatePlan(): array
    {
        $entities = [];

        foreach ($this->getRawList($this->getEligibleRatePlanEndpoint()) as $item) {
            /** @var \Apigee\Edge\Entity\EntityInterface $tmp */
            $tmp = $this->getEntitySerializer()->denormalize(
                $item,
                AcceptedRatePlanInterface::class,
                'json'
            );
            $entities[$tmp->id()] = $tmp;
        }

        return $entities;
    }
}
