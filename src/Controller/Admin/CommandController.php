<?php

namespace App\Controller\Admin;

use App\Entity\Status;
use App\Form\StatusSearchType;
use App\Form\SearchCommandType;
use App\Repository\StatusRepository;
use App\Repository\CommandRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;

/**
 * @Route("admin/commande", name="command_")
 */
class CommandController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index(
        CommandRepository $commandRepository,
        StatusRepository $statusRepository,
        HttpFoundationRequest $request
    ): Response {

       // $statusInfo = 'En cours';
        $form = $this->createForm(SearchCommandType::class);
        $form->handleRequest($request);
        //$status = new Status();
        $formStatus = $this->createForm(StatusSearchType::class);
        $formStatus->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $search = $form->getData()['search'];
            $commands = $commandRepository->findLikeProjectName($search);
        } else {
            $commands = $commandRepository->findAll();
        }

//Expected argument of type "string", "object" given at property path "name".

        if ($formStatus->isSubmitted() &&  $formStatus->isValid()) {
            $input = $formStatus->getData()['input'];

            $commands = $commandRepository->findByStatus(['status' => $input]);
        }
        //Notice: Trying to access array offset on value of type null

        //$status = $statusRepository->findOneByName(['name'=>$statusInfo]);
        //$commands = $commandRepository->findByStatus(['status'=> $status->getId()]);
        return $this->render('admin/command/index.html.twig', [
            'commands' => $commands,
            'status' => $statusRepository->findAll(),
            'form' => $form->createView(),
            'formStatus' => $formStatus->createView(),
        ]);
    }
}
