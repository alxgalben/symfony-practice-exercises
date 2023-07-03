<?php

namespace App\Controller;

use App\Entity\Participant;
use App\Form\ParticipantFormType;
use App\Repository\ParticipantRepository;
use App\Repository\ReceiptCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    /**
     * @Route("/main", name="homepage")
     */
    public function index(Request $request, EntityManagerInterface $em, ParticipantRepository $participantRepository): Response
    {
        $participant = new Participant();
        $participantForm = $this->createForm(ParticipantFormType::class, $participant);
        $participantForm->handleRequest($request);

        if($participantForm->isSubmitted() && $participantForm->isValid()){
            $minute = (int)date('i');
            $prizes = array('casti', 'ghiozdan', 'mouse');

            if($minute < 15) {
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
        return $this->render('main/index.html.twig', [
            'participantForm' => $participantForm->createView()
        ]);
    }
}
