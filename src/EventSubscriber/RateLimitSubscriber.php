<?php

namespace App\EventSubscriber;

use App\Repository\RateLimitRepository;
use DateTimeImmutable;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Doctrine\ORM\EntityManagerInterface;

class RateLimitSubscriber implements EventSubscriberInterface
{
    private $rateLimitRepository;
    private $entityManager;

    public function __construct(RateLimitRepository $rateLimitRepository, EntityManagerInterface $entityManager)
    {
        $this->rateLimitRepository = $rateLimitRepository;
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

// Check if the request path contains '/api'
        if (strpos($request->getPathInfo(), '/api') !== false) {
            $ipAddress = $request->getClientIp();
            $rateLimit = $this->rateLimitRepository->findOneBy(['ipAddress' => $ipAddress]);
            $currentDate = new DateTimeImmutable();

            if (!$rateLimit) {
                $rateLimit = new RateLimit();
                $rateLimit->setIpAddress($ipAddress);
                $rateLimit->setRequestCount(1);
                $rateLimit->setLastRequestAt($currentDate);
            } else {
                $timeDifference = $currentDate->getTimestamp() - $rateLimit->getLastRequestAt()->getTimestamp();

                if ($timeDifference < 60) {
                    if ($rateLimit->getRequestCount() >= 10) {
                        throw new TooManyRequestsHttpException(60, 'Too Many Requests');
                    }
                    $rateLimit->setRequestCount($rateLimit->getRequestCount() + 1);
                } else {
                    $rateLimit->setRequestCount(1);
                }

                $rateLimit->setLastRequestAt($currentDate);
            }

            $this->entityManager->persist($rateLimit);
            $this->entityManager->flush();
        }
    }
}
