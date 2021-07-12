<?php

namespace App\Controller;

use App\Entity\Command;
use App\Entity\Member;
use App\Entity\User;
use App\Form\MemberType;
use App\Repository\MemberRepository;
use App\Service\GameCard;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Cropperjs\Factory\CropperInterface;
use Symfony\UX\Cropperjs\Form\CropperType;

/**
 * @Route("/creez-votre-jeu/membre")
 */
class MemberController extends AbstractController
{
    /**
     * @Route("/{command<^[0-9]+$>}", name="member_index", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function index(Command $command, MemberRepository $memberRepository, GameCard $gameCard): Response
    {

        /** @var User */
        $user = $this->getUser();
        if (!$user->getCommands()->contains($command)) {
            throw $this->createAccessDeniedException("Vous n'avez pas accès à cette commande");
        }

        $priceGame = 0;
        try {
            $priceGame = $gameCard->priceGame($command);
        } catch (Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->render('member/index.html.twig', [
            'command' => $command,
            'members' => $memberRepository->findBy(['command' => $command->getId()], ['cardNumber' => 'ASC']),
            'priceGame' => $priceGame,
        ]);
    }

    /**
     * @Route("/new/{command<^[0-9]+$>}", name="member_new", methods={"GET","POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function new(Command $command, Request $request): Response
    {
        $member = new Member();

        /** @var User */
        $user = $this->getUser();
        if (!$user->getCommands()->contains($command)) {
            throw $this->createAccessDeniedException("Vous n'avez pas accès à cette commande");
        }

        if (count($command->getMembers() ?? []) >= GameCard::GAME_MAX) {
            $this->addFlash('danger', 'Vous avez atteint la limite de ' . GameCard::GAME_MAX . ' membres.');
            return $this->redirectToRoute('member_index', ['command' => $command->getId() ?? []]);
        }

        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $member->setCommand($command);
            $member->setName(strtoupper($member->getname()));
            $member->setCardNumber(count($command->getMembers() ?? []) + 1);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($member);
            $entityManager->flush();

            return $this->redirectToRoute('member_crop', [
                'command' => $command->getId(),
                'member' => $member->getId(),
            ]);
        }

        return $this->render('member/new.html.twig', [
            'member' => $member,
            'form' => $form->createView(),
            'command' => $command,
        ]);
    }

    /**
     * @Route("/crop/{command<^[0-9]+$>}/{member<^[0-9]+$>}", name="member_crop", methods={"GET","POST"})
     */
    public function crop(Command $command, Member $member, Request $request, CropperInterface $cropper): Response
    {
        /**
         * @var string
         */
        $fileDirectory = $this->getParameter('public_directory');
        /**
         * @var string
         */
        $fileUpload = $this->getParameter('upload_member_directory');
        $filename = $fileDirectory . $fileUpload . $member->getPicture();
        $crop = $cropper->createCrop($filename);
        $crop->setCroppedMaxSize(1500, 2000);

        $form = $this->createFormBuilder(['crop' => $crop])
            ->add('crop', CropperType::class, [
                'public_url' => $fileUpload . $member->getPicture(),
                'aspect_ratio' => 1800 / 2000,
            ])
            ->add('validate', SubmitType::class, [
                'label' => 'Valider',
            ])
            ->getForm()
        ;

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $encoded = ($crop->getCroppedImage());
            //GD Library extension not available with this PHP installation.
            // Be careful, here we are using PHP 7.4, if you change to 8.0, an error can occur
            /** @phpstan-ignore-next-line */
            $resource = (imagecreatefromstring($encoded));
            /** @phpstan-ignore-next-line */
            imagejpeg($resource, $filename);
            return $this->redirectToRoute('member_index', ['command' => $command->getId()]);
        }
        return $this->render('member/crop.html.twig', [
            'form' => $form->createView(),
            'command' => $command,
        ]);
    }

    /**
     * @Route("/up/{command<^[0-9]+$>}/{member<^[0-9]+$>}", name="member_up_cardnumber", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function upCardNumber(
        Command $command,
        Member $member,
        Request $request,
        MemberRepository $memberRepository
    ): Response {

        /** @var User */
        $user = $this->getUser();
        if (!$user->getCommands()->contains($command)) {
            throw $this->createAccessDeniedException("Vous n'avez pas accès à cette commande");
        }

        $numCardMember = $member->getCardNumber();
        if ($numCardMember != count($command->getMembers())) {
            $memberDown = new Member();
            $entityManager = $this->getDoctrine()->getManager();

            /** @var Member */
            $memberDown = $memberRepository->findOneBy(['command' => $command->getId(),
            'cardNumber' => ($numCardMember + 1)]);
            $memberDown->setCardNumber($numCardMember);
            $entityManager->persist($memberDown);

            $member->setCardNumber($numCardMember + 1);
            $entityManager->persist($member);
            $entityManager->flush();
        } else {
            $this->addFlash('danger', 'Vous êtes au nombre maximal');
        }

        return $this->redirectToRoute('member_index', ['command' => $command->getId()]);
    }

    /**
     * @Route("/Down/{command}/{member}", name="member_down_cardnumber", methods={"GET","POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function downCardNumber(
        Command $command,
        Member $member,
        Request $request,
        MemberRepository $memberRepository
    ): Response {
        /** @var User */
        $user = $this->getUser();
        if (!$user->getCommands()->contains($command)) {
            throw $this->createAccessDeniedException("Vous n'avez pas accès à cette commande");
        }
        $numCardMember = $member->getCardNumber();

        if ($numCardMember != 1) {
            $memberUp = new Member();
            $entityManager = $this->getDoctrine()->getManager();

            /** @var Member */
            $memberUp = $memberRepository->findOneBy(['command' => $command->getId(),
            'cardNumber' => ($numCardMember - 1)]);

            $memberUp->setCardNumber($numCardMember);
            $entityManager->persist($memberUp);

            $member->setCardNumber($numCardMember - 1);
            $entityManager->persist($member);
            $entityManager->flush();
        } else {
            $this->addFlash('danger', 'Vous êtes déjà au nombre minimal');
        }
        return $this->redirectToRoute('member_index', ['command' => $command->getId()]);
    }

    /**
     * @Route("/{id}/edit", name="member_edit", methods={"GET","POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function edit(Request $request, Member $member): Response
    {

        $form = $this->createForm(MemberType::class, $member);
        $form->handleRequest($request);

        /**
         * @var Command
         */
        $command = $member->getCommand();
        $commandId = $command->getId();

        /** @var User */
        $user = $this->getUser();
        if (!$user->getCommands()->contains($command)) {
            throw $this->createAccessDeniedException("Vous n'avez pas accès à cette commande");
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $member->setCommand($command);
            $member->setName(strtoupper($member->getname()));
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($member);
            $entityManager->flush();
            return $this->redirectToRoute('member_crop', [
                'command' => $command->getId(),
                'member' => $member->getId(),
            ]);
        }

        return $this->render('member/edit.html.twig', [
            'member' => $member,
            'command' => $commandId,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="member_delete", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function delete(Request $request, Member $member): Response
    {
        /**
         * @var Command
         */
        $command = $member->getCommand();
        $commandId = $command->getId();

        /** @var User */
        $user = $this->getUser();
        if (!$user->getCommands()->contains($command)) {
            throw $this->createAccessDeniedException("Vous n'avez pas accès à cette commande");
        }

        if ($this->isCsrfTokenValid('delete' . $member->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($member);
            $entityManager->flush();
        }

        return $this->redirectToRoute('member_index', ['command' => $commandId]);
    }

    /**
     * @Route("/{id}", name="member_show", methods={"GET"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function show(Member $member): Response
    {
        return $this->render('member/show.html.twig', [
            'member' => $member,
        ]);
    }
}
