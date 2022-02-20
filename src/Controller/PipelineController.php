<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PipelineController extends AbstractController
{
    #[Route('/pipeline', methods: ['POST'])]
    public function index(Request $request): Response
    {
        $params = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $user = $params->user ?? null;
        $objectAttributes = $params->object_attributes ?? null;

        $checkStatus = (isset($objectAttributes->status) && $objectAttributes->status === 'failed');
        $checkUser = (isset($user->username) && $user->username === $this->getParameter('pipeline.username'));

        if (!$checkStatus) {
            return new JsonResponse(['status' => 'Pipelined ' . $objectAttributes->status ?? '-']);
        }

        if (!$checkUser) {
            return new JsonResponse(['message' => 'Pipeline for a different user.']);
        }

        try {
            $this->publishToMqtt();
        } catch (\Exception $ex) {
            return new JsonResponse(['message' => $ex->getMessage()], 500);
        }

        return new JsonResponse(['message' => 'Published to topic ' . $this->getParameter('mqtt.topic')]);
    }

    private function publishToMqtt(): void
    {
        $host = $this->getParameter('mqtt.host') ?? 'broker.hivemq.com';
        $port = $this->getParameter('mqtt.port') ?? 1883;
        $topic = $this->getParameter('mqtt.topic') ?? 'path/to/topic';
        $message = '1';

        $mqtt = new \PhpMqtt\Client\MqttClient($host, $port);
        $mqtt->connect();
        $mqtt->publish($topic, $message, 1);
        $mqtt->disconnect();
    }
}