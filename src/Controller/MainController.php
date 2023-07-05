<?php

namespace App\Controller;

use App\Entity\Participant;
use App\Form\ParticipantFormType;
use App\Repository\ParticipantRepository;
use App\Repository\ReceiptCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MainController extends AbstractController
{

    /*public function indexApi(Request $request, EntityManagerInterface $em, ParticipantRepository $participantRepository, ValidatorInterface $validator, HttpClientInterface $httpClient): Response
    {

        //$this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);

        $participant = new Participant();
        $participantForm = $this->createForm(ParticipantFormType::class, $participant, ['csrf_protection' => false]);
        $participantForm->handleRequest($request);
        $participantForm->submit($data);

        if ($participantForm->isSubmitted() && $participantForm->isValid()) {
            $minute = (int)date('i');
            $prizes = array('casti', 'ghiozdan', 'mouse');

            if ($minute < 15) {
                $randomWordIndex = array_rand($prizes);
                $randomWord = $prizes[$randomWordIndex];
                $this->addFlash('success', 'You have won a special prize: ' . $randomWord);
            }

            $currentDate = new \DateTimeImmutable();
            $participant->setSubmittedAt($currentDate);
            $userReceiptCountToday = $participantRepository->countUserReceiptCountToday($currentDate, $participant->getEmail());

            if ($userReceiptCountToday >= 2) {
                $this->addFlash('warning', 'You have already entered 2 receipts today. Come back tomorrow');
            }

            if ($userReceiptCountToday <= 2) {
                $em->persist($participant);
                $em->flush();
            }
            //dd($participant);
        }

        if (!$participantForm->isValid()) {
            $errors = [];
            foreach ($participantForm->getErrors(true, true) as $formError) {
                $field = $formError->getOrigin()->getName();
                $message = $formError->getMessage();
                $errors[] = sprintf('Field "%s": %s', $field, $message);
            }
            return new JsonResponse([
                'success' => false,
                'errors' => $errors
            ]);
        }

        try {
            $response = $httpClient->request('POST', 'http://dev.tema2/main-api', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            $responseData = $response->toArray();

            if ($response->getStatusCode() === 200 && $responseData['success'] === true) {
                $this->addFlash('success', $responseData['message']);
            } else {
                $errors = $responseData['errors'] ?? ['An error occurred.'];
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
        } catch (ClientExceptionInterface $exception) {
            // Handle any exceptions that may occur during the API request
            $this->addFlash('error', 'An error occurred while communicating with the API.');
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Datele au fost salvate cu succes!'
        ]);
    }*/

    /**
     * @Route("/main", name="homepage")
     */
    public function index(Request $request, EntityManagerInterface $em, ParticipantRepository $participantRepository, ValidatorInterface $validator): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_USER');
        $participant = new Participant();
        $participantForm = $this->createForm(ParticipantFormType::class, $participant);

        return $this->render('main/index.html.twig', [
            'participantForm' => $participantForm->createView(),
        ]);
    }

    /**
     * @Route("/api", name="api")
     */
    public function indexApi(Request $request, EntityManagerInterface $em, ParticipantRepository $participantRepository, ValidatorInterface $validator): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_USER');
        $participant = new Participant();
        $participantForm = $this->createForm(ParticipantFormType::class, $participant);
        $participantForm->handleRequest($request);

        if ($participantForm->isSubmitted()) {
            // Check if the form is valid
            if ($participantForm->isValid()) {

                $minute = (int)date('i');
                $prizes = array('casti', 'ghiozdan', 'mouse');

                if ($minute < 15) {
                    $randomWordIndex = array_rand($prizes);
                    $randomWord = $prizes[$randomWordIndex];
                    $this->addFlash('success', 'You have won a special prize: ' . $randomWord);
                }

                $currentDate = new \DateTimeImmutable();
                $participant->setSubmittedAt($currentDate);
                $userReceiptCountToday = $participantRepository->countUserReceiptCountToday($currentDate, $participant->getEmail());

                if ($userReceiptCountToday >= 2) {
                    $this->addFlash('warning', 'You have already entered 2 receipts today. Come back tomorrow');
                }

                if ($userReceiptCountToday <= 2) {
                    $em->persist($participant);
                    $em->flush();
                }

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Datele au fost salvate cu succes!',
                ], 200);
            } else {
                $errors = [];
                foreach ($participantForm->getErrors(true, true) as $formError) {
                    $field = $formError->getOrigin()->getName();
                    $message = $formError->getMessage();
                    $errors[] = sprintf('Field "%s": %s', $field, $message);
                }
                return new JsonResponse([
                    'success' => false,
                    'errors' => $errors,
                ], 200);
            }

        }

        return new JsonResponse([
            'success' => false,
            'errors' => [],
        ], 400);
    }

    /**
     * @Route("/login", name="login")
     */
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        /*if($request->getMethod() === 'POST'){
            return $this->redirectToRoute('listing');
        }*/

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'lastUsername' => $lastUsername,
            'error' => $error
        ]);
    }

    /**
     * @Route("/logout", name="logout")
     */

    public function logout(AuthenticationUtils $authenticationUtils)
    {

    }

    /**
     * @Route("/export-csv/", name="export-csv")
     */

    public function exportCsv(Request $request, EntityManagerInterface $entityManager)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $tmpFileName = (new Filesystem())->tempnam(sys_get_temp_dir(), 'sb_');
        $tmpFile = fopen($tmpFileName, 'wb+');

        $startDateParam = $request->query->get('start_date');
        $endDateParam = $request->query->get('end_date');

        if (!$startDateParam || !$endDateParam) {
            throw new \InvalidArgumentException('Both start_date and end_date parameters are required.');

        }

        $startDate = \DateTime::createFromFormat('Y-m-d', $startDateParam);
        //dd($startDate);
        $endDate = \DateTime::createFromFormat('Y-m-d', $endDateParam);


        if (!$startDate instanceof \DateTime || !$endDate instanceof \DateTime) {
            throw new \InvalidArgumentException('Invalid date format. The dates should be in the format: "YYYY-MM-DD".');
        }
        $startDate->setTime(0, 0, 1);
        $endDate->setTime(23, 59, 59);

        if (!\is_resource($tmpFile)) {
            throw new \RuntimeException('Unable to create a temporary file.');
        }

        $data = [
            ['id', 'firstName', 'lastName', 'email', 'phoneNumber', 'receiptCode'],
        ];

        $repository = $entityManager->getRepository(Participant::class);
        $registrations = $repository->findByDateInterval($startDate, $endDate);

        foreach ($registrations as $registration) {
            $data[] = [
                strtoupper((string)$registration->getId()),
                strtoupper((string)$registration->getFirstName()),
                strtoupper((string)$registration->getLastName()),
                strtoupper((string)$registration->getEmail()),
                strtoupper((string)$registration->getPhoneNumber()),
                strtoupper((string)$registration->getReceipt()->getName()),
            ];
        }

        foreach ($data as $el) {
            fputcsv($tmpFile, $el, ';', '"');
        }

        $response = $this->file($tmpFileName, 'export.csv');
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="export.csv"');

        fclose($tmpFile);

        return $response;
    }

    /**
     * @Route("/winner/{id}", name="winner")
     */
    public function markWinner(Request $request, EntityManagerInterface $em, $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $participant = $em->getRepository(Participant::class)->find($id);

        if (!$participant) {
            throw $this->createNotFoundException('Participant not found');
        }

        $participant->setIsWinner(true);
        $em->flush();

        return $this->redirectToRoute('listing');
    }

    /**
     * @Route("/winners-week", name="winner-week")
     */
    public function winnerPerWeek(Request $request, EntityManagerInterface $em, ParticipantRepository $participantRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $selectedWeek = $request->query->get('week', 1);
        //$winnersPerWeek = $em->getRepository(Participant::class)->findWinnersPerWeek();
        $winnersPerWeek = $em->getRepository(Participant::class)->findWinnersPerWeekByNumber($selectedWeek);
        return $this->render('listing/winners_per_week.html.twig', [
            'winnersPerWeek' => $winnersPerWeek
        ]);
    }
}
