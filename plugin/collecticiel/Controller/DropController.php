<?php
/**
 * Created by : Vincent SAISSET
 * Date: 22/08/13
 * Time: 09:30.
 */
namespace Innova\CollecticielBundle\Controller;

use Innova\CollecticielBundle\Entity\Correction;
use Innova\CollecticielBundle\Entity\Document;
use Innova\CollecticielBundle\Entity\Drop;
use Innova\CollecticielBundle\Entity\Dropzone;
use Innova\CollecticielBundle\Entity\ReturnReceipt;
use Innova\CollecticielBundle\Entity\ReturnReceiptType;
use Innova\CollecticielBundle\Event\Log\LogCorrectionUpdateEvent;
use Innova\CollecticielBundle\Event\Log\LogDropEndEvent;
use Innova\CollecticielBundle\Event\Log\LogDropStartEvent;
use Innova\CollecticielBundle\Event\Log\LogDropReportEvent;
use Innova\CollecticielBundle\Form\CorrectionReportType;
use Innova\CollecticielBundle\Form\DropType;
use Innova\CollecticielBundle\Form\DocumentType;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Library\Resource\ResourceCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Innova\CollecticielBundle\Event\Log\LogDropzoneReturnReceiptEvent;
use Innova\CollecticielBundle\Event\Log\LogDropzoneValidateDocumentEvent;

class DropController extends DropzoneBaseController
{
    /**
     * @Route(
     *      "/drop/{resourceId}/user/{userId}", name="innova_collecticiel_drop_switch_admin", requirements={"resourceId" = "\d+", "userId" = "\d+"}
     * )
     * @Route(
     *      "/{resourceId}/drop/user/{userId}", name="innova_collecticiel_drop_switch", requirements={"resourceId" = "\d+", "userId" = "\d+"}
     * )
     * @Route(
     *      "/{resourceId}/drop", name="innova_collecticiel_drop", requirements={"resourceId" = "\d+"}
     * )
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @ParamConverter("user", isOptional="true", class="ClarolineCoreBundle:User",options={"id" = "userId"})
     * @Template()
     */
    public function dropAction(Dropzone $dropzone, User $user = null)
    {
        $dropzoneManager = $this->get('innova.manager.dropzone_manager');
        $dropManager = $this->get('innova.manager.drop_manager');
        $dropzoneVoter = $this->get('innova.manager.dropzone_voter');
        $roleManager = $this->get('claroline.manager.role_manager');
        $em = $this->getDoctrine()->getManager();
        $translator = $this->get('translator');
        $dropRepo = $em->getRepository('InnovaCollecticielBundle:Drop');
        $flashbag = $this->getRequest()->getSession()->getFlashBag();

        // on teste si l'utilisateur à le droit d'ouvrir le dropzone
        $dropzoneVoter->isAllowToOpen($dropzone);

        if (!$user) {
            $user = $this->get('security.token_storage')->getToken()->getUser();
        }

        // on vérifie que la copie n'est pas terminée pour le dropzone et utilisateur donnée
        if ($dropRepo->findOneBy(array('dropzone' => $dropzone, 'user' => $user, 'finished' => true)) !== null) {
            $flashbag->add('error', $translator->trans('You ve already made ​​your copy for this review', array(), 'innova_collecticiel'));
            $url = $this->generateUrl('innova_collecticiel_open', array('resourceId' => $dropzone->getId()));

            return $this->redirect($url);
        }

        // on récupère le drop existant ou on le créé s'il n'existe pas.
        $notFinishedDrop = $dropRepo->findOneBy(array('dropzone' => $dropzone, 'user' => $user, 'finished' => false));
        if ($notFinishedDrop === null) {
            $notFinishedDrop = $dropManager->create($dropzone, $user);
            $event = new LogDropStartEvent($dropzone, $notFinishedDrop);
            $this->dispatch($event);
        }

        $form = $this->createForm(new DropType(), $notFinishedDrop);
        $form_url = $this->createForm(new DocumentType(), null, array('documentType' => 'url'));
        $form_file = $this->createForm(new DocumentType(), null, array('documentType' => 'file'));
        $form_resource = $this->createForm(new DocumentType(), null, array('documentType' => 'resource'));
        $form_text = $this->createForm(new DocumentType(), null, array('documentType' => 'text'));
        $drop = $notFinishedDrop;

        if ($this->getRequest()->isMethod('POST')) {
            $form->handleRequest($this->getRequest());

            if (count($notFinishedDrop->getDocuments()) == 0) {
                $form->addError(new FormError('Add at least one document'));
            }

            if ($form->isValid()) {
                $em->persist($notFinishedDrop);
                $em->flush();

                $event = new LogDropEndEvent($dropzone, $notFinishedDrop, $roleManager);
                $this->dispatch($event);

                $flashbag->add('success', $translator->trans('Your copy has been saved', array(), 'innova_collecticiel'));
                $url = $this->generateUrl('innova_collecticiel_open', array('resourceId' => $dropzone->getId()));

                return $this->redirect($url);
            }
        }

        $allowedTypes = $dropzoneManager->getAllowedTypes($dropzone);
        $dropzoneProgress = $dropzoneManager->getDropzoneProgressByUser($dropzone, $user);
        $canEdit = $dropzoneVoter->checkEditRight($dropzone);
        $userNbTextToRead = array();

        $activeRoute = $this->getRequest()->attributes->get('_route');

        $collecticielOpenOrNot = $dropzoneManager->collecticielOpenOrNot($dropzone);

        $returnReceiptArray = array();
        // Tableau donnant pour chaque document le premier enseignant qui a commenté
        $teacherCommentDocArray = array();

        // Pour avoir l'accusé de réception (ou pas) de chaque document
        foreach ($drop->getDocuments() as $document) {
            // Récupération de l'accusé de réceptoin
            $returnReceiptType = $this->getDoctrine()
            ->getRepository('InnovaCollecticielBundle:ReturnReceipt')
            ->doneReturnReceiptForOneDocument($document);

            if (!empty($returnReceiptType)) {
                // Récupération de la valeur de l'accusé de réceptoin
                $returnReceiptArray[$document->getId()] = $returnReceiptType[0]->getReturnReceiptType()->getId();
            } else {
                $returnReceiptArray[$document->getId()] = 0;
            }

            // Récupération du premier enseignant qui a commenté ce document
            $teacherCommentDocArray[$document->getId()] = 0;
            $userComments = $this->getDoctrine()->getRepository('InnovaCollecticielBundle:Comment')
            ->teacherCommentDocArray($document);
            // Traitement du tableau
            $foundAdminComment = false;
            for ($indice = 0; $indice < count($userComments); ++$indice) {
                $ResourceNode = $dropzone->getResourceNode();
                $workspace = $ResourceNode->getWorkspace();
                // getting the  Manager role
                $this->role_manager = $this->get('claroline.manager.role_manager');
                $role = $this->role_manager->getWorkspaceRolesForUser($userComments[$indice]->getUser(), $workspace);

                // Traitement du tableau
                for ($indiceRole = 0; $indiceRole < count($role); ++$indiceRole) {
                    $roleName = $role[$indiceRole]->getName();
                    if (strpos('_'.$roleName, 'ROLE_WS_MANAGER') === 1) {
                        if ($foundAdminComment == false) {
                            $teacherCommentDocArray[$document->getId()] = 1;
                            $foundAdminComment = true;
                        }
                    }
                }
            }
        }

        return array(
            'workspace' => $dropzone->getResourceNode()->getWorkspace(),
            '_resource' => $dropzone,
            'dropzone' => $dropzone,
            'drop' => $drop,
            'form' => $form->createView(),
            'form_url' => $form_url->createView(),
            'form_file' => $form_file->createView(),
            'form_resource' => $form_resource->createView(),
            'form_text' => $form_text->createView(),
            'allowedTypes' => $allowedTypes,
            'dropzoneProgress' => $dropzoneProgress,
            'adminInnova' => $canEdit,
            'userNbTextToRead' => $userNbTextToRead,
            'activeRoute' => $activeRoute,
            'collecticielOpenOrNot' => $collecticielOpenOrNot,
            'returnReceiptArray' => $returnReceiptArray,
            'teacherCommentDocArray' => $teacherCommentDocArray,
        );
    }

    private function addDropsStats($dropzone, $array)
    {
        $dropRepo = $this->getDoctrine()->getManager()->getRepository('InnovaCollecticielBundle:Drop');
        $array['nbDropCorrected'] = $dropRepo->countDropsFullyCorrected($dropzone);
        $array['nbDrop'] = $dropRepo->countDrops($dropzone);

        return $array;
    }

    /**
     * @Route(
     *      "/{resourceId}/drops/by/user",
     *      name="innova_collecticiel_drops_by_user",
     *      requirements={"resourceId" = "\d+"},
     *      defaults={"page" = 1}
     * )
     * @Route(
     *      "/{resourceId}/drops/by/user/{page}",
     *      name="innova_collecticiel_drops_by_user_paginated",
     *      requirements={"resourceId" = "\d+", "page" = "\d+"},
     *      defaults={"page" = 1}
     * )
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @Template()
     */
    public function dropsByUserAction($dropzone, $page)
    {
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);

        $dropRepo = $this->getDoctrine()->getManager()->getRepository('InnovaCollecticielBundle:Drop');
        $dropsQuery = $dropRepo->getDropsFullyCorrectedOrderByUserQuery($dropzone);

        $countUnterminatedDrops = $dropRepo->countUnterminatedDropsByDropzone($dropzone->getId());
        $adapter = new DoctrineORMAdapter($dropsQuery);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage(DropzoneBaseController::DROP_PER_PAGE);
        try {
            $pager->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            if ($page > 0) {
                return $this->redirect(
                    $this->generateUrl(
                        'innova_collecticiel_drops_by_user_paginated',
                        array(
                            'resourceId' => $dropzone->getId(),
                            'page' => $pager->getNbPages(),
                        )
                    )
                );
            } else {
                throw new NotFoundHttpException();
            }
        }

        return $this->addDropsStats($dropzone, array(
            'workspace' => $dropzone->getResourceNode()->getWorkspace(),
            '_resource' => $dropzone,
            'unterminated_drops' => $countUnterminatedDrops,
            'dropzone' => $dropzone,
            'pager' => $pager,
        ));
    }

    /**
     * @Route(
     *      "/{resourceId}/unlock/{userId}",
     *      name="innova_collecticiel_unlock_user",
     *      requirements={"resourceId" = "\d+", "userId" = "\d+"}
     * )
     * @ParamConverter("dropzone",class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     *
     * @param \Innova\CollecticielBundle\Entity\Dropzone $dropzone
     * @param $userId
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @internal param $user
     * @internal param $userId
     */
    private function unlockUser(Dropzone $dropzone, $userId)
    {
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);
        $dropRepo = $this->getDoctrine()->getManager()->getRepository('InnovaCollecticielBundle:Drop');
        $drop = $dropRepo->getDropByUser($dropzone->getId(), $userId);
        if ($drop != null) {
            $drop->setUnlockedUser(true);
        }
        $em = $this->getDoctrine()->getManager();
        $em->merge($drop);
        $em->flush();

        return $this->redirect(
            $this->generateUrl(
                'innova_collecticiel_examiners',
                array(
                    'resourceId' => $dropzone->getId(),
                )
            )
        );
    }

    /**
     * @Route(
     *      "/{resourceId}/unlock/all",
     *      name="innova_collecticiel_unlock_all_user",
     *      requirements={"resourceId" = "\d+"}
     * )
     * @ParamConverter("dropzone",class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     *
     * @param \Innova\CollecticielBundle\Entity\Dropzone $dropzone
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @internal param $user
     * @internal param $userId
     */
    private function unlockUsers(Dropzone $dropzone)
    {
        return $this->unlockOrLockUsers($dropzone, true);
    }

    /**
     * @Route(
     *      "/{resourceId}/unlock/cancel",
     *      name="innova_collecticiel_unlock_cancel",
     *      requirements={"resourceId" = "\d+"}
     * )
     * @ParamConverter("dropzone",class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     *
     * @param \Innova\CollecticielBundle\Entity\Dropzone $dropzone
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @internal param $user
     * @internal param $userId
     */
    private function unlockUsersCancel(Dropzone $dropzone)
    {
        return $this->unlockOrLockUsers($dropzone, false);
    }

    /**
     *  Factorised function for lock & unlock users in a dropzone.
     *
     * @param Dropzone $dropzone
     * @param bool     $unlock
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    private function unlockOrLockUsers(Dropzone $dropzone, $unlock = true)
    {
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);

        $dropRepo = $this->getDoctrine()->getManager()->getRepository('InnovaCollecticielBundle:Drop');
        $drops = $dropRepo->findBy(array('dropzone' => $dropzone->getId(), 'unlockedUser' => !$unlock));

        foreach ($drops as $drop) {
            $drop->setUnlockedUser($unlock);
        }
        $em = $this->getDoctrine()->getManager();
        $em->flush();

        return $this->redirect(
            $this->generateUrl(
                'innova_collecticiel_examiners',
                array(
                    'resourceId' => $dropzone->getId(),
                )
            )
        );
    }

    /**
     * @Route(
     *      "/{resourceId}/drops",
     *      name="innova_collecticiel_drops",
     *      requirements={"resourceId" = "\d+"},
     *      defaults={"page" = 1}
     * )
     * @Route(
     *      "/{resourceId}/drops/by/default",
     *      name="innova_collecticiel_drops_by_default",
     *      requirements={"resourceId" = "\d+"},
     *      defaults={"page" = 1}
     * )
     * @Route(
     *      "/{resourceId}/drops/by/default/{page}",
     *      name="innova_collecticiel_drops_by_default_paginated",
     *      requirements={"resourceId" = "\d+", "page" = "\d+"},
     *      defaults={"page" = 1}
     * )
     *
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @Template()
     **/
    public function dropsByDefaultAction($dropzone, $page)
    {
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);

        $dropRepo = $this->getDoctrine()->getManager()->getRepository('InnovaCollecticielBundle:Drop');
        $dropsQuery = $dropRepo->getDropsFullyCorrectedOrderByReportAndDropDateQuery($dropzone);

        $adapter = new DoctrineORMAdapter($dropsQuery);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage(DropzoneBaseController::DROP_PER_PAGE);
        $countUnterminatedDrops = $dropRepo->countUnterminatedDropsByDropzone($dropzone->getId());
        try {
            $pager->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            if ($page > 0) {
                return $this->redirect(
                    $this->generateUrl(
                        'innova_collecticiel_drops_by_user_paginated',
                        array(
                            'resourceId' => $dropzone->getId(),
                            'page' => $pager->getNbPages(),
                        )
                    )
                );
            } else {
                throw new NotFoundHttpException();
            }
        }

        return $this->addDropsStats($dropzone, array(
            'workspace' => $dropzone->getResourceNode()->getWorkspace(),
            '_resource' => $dropzone,
            'dropzone' => $dropzone,
            'pager' => $pager,
            'unterminated_drops' => $countUnterminatedDrops,
        ));
    }

    /**
     * @Route(
     *      "/{resourceId}/drops/by/report",
     *      name="innova_collecticiel_drops_by_report",
     *      requirements={"resourceId" = "\d+"},
     *      defaults={"page" = 1}
     * )
     * @Route(
     *      "/{resourceId}/drops/by/report/{page}",
     *      name="innova_collecticiel_drops_by_report_paginated",
     *      requirements={"resourceId" = "\d+", "page" = "\d+"},
     *      defaults={"page" = 1}
     * )
     *
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @Template()
     */
    public function dropsByReportAction($dropzone, $page)
    {
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);

        $dropRepo = $this->getDoctrine()->getManager()->getRepository('InnovaCollecticielBundle:Drop');
        $dropsQuery = $dropRepo->getDropsFullyCorrectedReportedQuery($dropzone);

        $adapter = new DoctrineORMAdapter($dropsQuery);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage(DropzoneBaseController::DROP_PER_PAGE);
        $countUnterminatedDrops = $dropRepo->countUnterminatedDropsByDropzone($dropzone->getId());

        try {
            $pager->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            if ($page > 0) {
                return $this->redirect(
                    $this->generateUrl(
                        'innova_collecticiel_drops_by_user_paginated',
                        array(
                            'resourceId' => $dropzone->getId(),
                            'page' => $pager->getNbPages(),
                        )
                    )
                );
            } else {
                throw new NotFoundHttpException();
            }
        }

        return $this->addDropsStats($dropzone, array(
            'workspace' => $dropzone->getResourceNode()->getWorkspace(),
            '_resource' => $dropzone,
            'dropzone' => $dropzone,
            'pager' => $pager,
            'unterminated_drops' => $countUnterminatedDrops,
        ));
    }

    /**
     * @Route(
     *      "/{resourceId}/drops/by/date",
     *      name="innova_collecticiel_drops_by_date",
     *      requirements={"resourceId" = "\d+"},
     *      defaults={"page" = 1}
     * )
     * @Route(
     *      "/{resourceId}/drops/by/date/{page}",
     *      name="innova_collecticiel_drops_by_date_paginated",
     *      requirements={"resourceId" = "\d+", "page" = "\d+"},
     *      defaults={"page" = 1}
     * )
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @Template()
     */
    public function dropsByDateAction($dropzone, $page)
    {
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);

        $dropRepo = $this->getDoctrine()->getManager()->getRepository('InnovaCollecticielBundle:Drop');
        $dropsQuery = $dropRepo->getDropsFullyCorrectedOrderByDropDateQuery($dropzone);
        $countUnterminatedDrops = $dropRepo->countUnterminatedDropsByDropzone($dropzone->getId());

        $adapter = new DoctrineORMAdapter($dropsQuery);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage(DropzoneBaseController::DROP_PER_PAGE);
        try {
            $pager->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            if ($page > 0) {
                return $this->redirect(
                    $this->generateUrl(
                        'innova_collecticiel_drops_by_date_paginated',
                        array(
                            'resourceId' => $dropzone->getId(),
                            'page' => $pager->getNbPages(),
                        )
                    )
                );
            } else {
                throw new NotFoundHttpException();
            }
        }

        return $this->addDropsStats($dropzone, array(
            'workspace' => $dropzone->getResourceNode()->getWorkspace(),
            '_resource' => $dropzone,
            'dropzone' => $dropzone,
            'pager' => $pager,
            'unterminated_drops' => $countUnterminatedDrops,
        ));
    }

    /**
     * @Route(
     *      "/{resourceId}/drops/awaiting",
     *      name="innova_collecticiel_drops_awaiting",
     *      requirements={"resourceId" = "\d+"},
     *      options={"expose"=true},
     *      defaults={"page" = 1}
     * )
     * @Route(
     *      "/{resourceId}/drops/awaiting/{page}",
     *      name="innova_collecticiel_drops_awaiting_paginated",
     *      requirements={"resourceId" = "\d+", "page" = "\d+"},
     *      defaults={"page" = 1}
     * )
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @Template()
     */
    public function dropsAwaitingAction($dropzone, $page)
    {
        $translator = $this->get('translator');
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);
        $dropzoneManager = $this->get('innova.manager.dropzone_manager');

        $dropzoneVoter = $this->get('innova.manager.dropzone_voter');

        $dropRepo = $this->getDoctrine()->getManager()->getRepository('InnovaCollecticielBundle:Drop');

        // dropsQuery : finished à TRUE et unlocked_drop à FALSE
        $dropsQuery = $dropRepo->getDropsAwaitingCorrectionQuery($dropzone, 1);

        // Nombre d'AR pour CE dropzone / Repo : ReturnReceipt
        $countReturnReceiptForDropzone = $this->getDoctrine()
                            ->getRepository('InnovaCollecticielBundle:ReturnReceipt')
                            ->countTextToRead($this->get('security.token_storage')->getToken()->getUser(),
                                              $dropzone
                            );

        $countUnterminatedDrops = $dropRepo->countUnterminatedDropsByDropzone($dropzone->getId());

        // Déclaration du compteur de documents sans accusé de réception
        $alertNbDocumentWithoutReturnReceipt = 0;
        $totalValideAndNotAdminDocs = 0;
        $countReturnReceiptForUserAndDropzone = 0;

        // Déclarations des nouveaux tableaux, qui seront passés à la vue
        $userToCommentCount = array();
        $userNbTextToRead = array();
        $haveReturnReceiptOrNotArray = array();
        $haveCommentOrNotArray = array();

        foreach ($dropzone->getDrops() as $drop) {

            // Calcul du compteur de documents sans accusé de réception

            /* InnovaERV : ajout pour calculer les 2 zones **/
            // Nombre de commentaires non lus / Repo : Comment
            $nbCommentsPerUser = $this->getDoctrine()
                                ->getRepository('InnovaCollecticielBundle:Comment')
                                ->countCommentNotRead($drop->getUser());

            // Nombre de demandes adressées / Repo : Document
            $nbTextToRead = $this->getDoctrine()
                                ->getRepository('InnovaCollecticielBundle:Document')
                                ->countTextToRead($drop->getUser(), $drop->getDropZone());

            // Nombre de demandes adressées / Repo : Document
            $countValideAndNotAdminDocs = $this->getDoctrine()
                                ->getRepository('InnovaCollecticielBundle:Document')
                                ->countValideAndNotAdminDocs($this->get('security.token_storage')->getToken()->getUser(),
                                $drop);

            // Nombre d'AR pour cet utilisateur et pour ce dropzone / Repo : ReturnReceiputtwment
            $haveReturnReceiptOrNot = $this->getDoctrine()
                                ->getRepository('InnovaCollecticielBundle:ReturnReceipt')
                                ->haveReturnReceiptOrNot(
                                    $this->get('security.token_storage')->getToken()->getUser(),
                                    $drop->getDropZone());

            $totalValideAndNotAdminDocs = $totalValideAndNotAdminDocs + $countValideAndNotAdminDocs;

            // Nombre d'AR pour cet utilisateur et pour ce dropzone / Repo : ReturnReceipt
            $countReturnReceiptForUserAndDropzone = $this->getDoctrine()
                                ->getRepository('InnovaCollecticielBundle:ReturnReceipt')
                                ->countTextToReadAll($this->get('security.token_storage')->getToken()->getUser(),
                                 $drop->getDropZone());
            $countReturnReceiptForUserAndDropzone = $countReturnReceiptForUserAndDropzone - 1;

            // Traitement du tableau
            for ($indice = 0; $indice <= $countReturnReceiptForUserAndDropzone; ++$indice) {
                $documentId = $haveReturnReceiptOrNot[$indice]->getDocument()->getId();
                $returnReceiptTypeId = $haveReturnReceiptOrNot[$indice]->getReturnReceiptType()->getId();
                $haveReturnReceiptOrNotArray[$documentId] = $returnReceiptTypeId;
            }

            // Boucle pour calcul si le document X a un commentaire déposé par l'enseignant
            foreach ($drop->getDocuments() as $document2) {
                if ($document2->getValidate() == 1) {
                    $documentId = $document2->getId();

                    // Ajout pour savoir si le document a un commentaire lu par l'enseignant
                    $commentReadForATeacherOrNot = $this->getDoctrine()
                                    ->getRepository('InnovaCollecticielBundle:Comment')
                                    ->commentReadForATeacherOrNot(
                                        $this->get('security.token_storage')->getToken()->getUser(),
                                        $documentId
                                    );

                    $commentReadForATeacherOrNot2 = $this->getDoctrine()
                                    ->getRepository('InnovaCollecticielBundle:Comment')
                                    ->commentReadForATeacherOrNot2(
                                        $this->get('security.token_storage')->getToken()->getUser(),
                                        $documentId
                                    );

                    $commentReadForATeacherOrNot3 = $this->getDoctrine()
                                    ->getRepository('InnovaCollecticielBundle:Comment')
                                    ->commentReadForATeacherOrNot3(
                                        $this->get('security.token_storage')->getToken()->getUser(),
                                        $documentId
                                    );

//                    var_dump("User : " . $this->get('security.token_storage')->getToken()->getUser()->getId());
//                    var_dump("Document : " . $documentId);
//                    var_dump("Compteur : créé élève lu admin " . $commentReadForATeacherOrNot .
//                    "+ élève " . $commentReadForATeacherOrNot2 .
//                    "+ créé admin lu admin " . $commentReadForATeacherOrNot3)
                    ;
                    $haveCommentOrNotArray[$documentId] = $commentReadForATeacherOrNot + $commentReadForATeacherOrNot2 + $commentReadForATeacherOrNot3;
    //                var_dump("Indice : " . $indice);
                }
            }

            // Affectations des résultats dans les tableaux
            $userToCommentCount[$drop->getUser()->getId()] = $nbCommentsPerUser;
            $userNbTextToRead[$drop->getUser()->getId()] = $nbTextToRead;
        }

        // Calcul du nombre de documents sans accusé de réception
        $alertNbDocumentWithoutReturnReceipt = $totalValideAndNotAdminDocs - $countReturnReceiptForDropzone;

        $adapter = new DoctrineORMAdapter($dropsQuery);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage(DropzoneBaseController::DROP_PER_PAGE);
        try {
            $pager->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            if ($page > 0) {
                return $this->redirect(
                    $this->generateUrl(
                        'innova_collecticiel_drops_awaiting_paginated',
                        array(
                            'resourceId' => $dropzone->getId(),
                            'page' => $pager->getNbPages(),
                        )
                    )
                );
            } else {
                throw new NotFoundHttpException();
            }
        }

        $adminInnova = $dropzoneVoter->checkEditRight($dropzone);
    /*    if ($this->get('security.context')->isGranted('ROLE_ADMIN' === true)) {
            $adminInnova = true;
        }*/

        if (count($pager) == 0) {
            $this->getRequest()->getSession()->getFlashBag()->add('success', $translator->trans('No copy waiting for correction', array(), 'innova_collecticiel'));
        }

        $collecticielOpenOrNot = $dropzoneManager->collecticielOpenOrNot($dropzone);

        //
        // Partie pour calculer les compteurs
        //

        // Tableau donnant pour chaque document le premier enseignant qui a commenté
        $teacherCommentDocArray = array();

        // Calcul du nombre d'AR en attente en prenant la même boucle que l'affichage de la liste.
        $alertNbDocumentWithoutReturnReceipt = 0;
        foreach ($pager->getcurrentPageResults() as $drop) {
            foreach ($drop->getDocuments() as $document) {

                // Récupération de l'accusé de réception
                $returnReceiptType = $this->getDoctrine()->getRepository('InnovaCollecticielBundle:ReturnReceipt')
                ->doneReturnReceiptForADocument($dropzone, $document);

                // Initialisation de la variable car un document peut ne pas avoir d'accusé de réception.
                $id = 0;

                if (!empty($returnReceiptType)) {
                    // Récupération de la valeur de l'accusé de réceptoin
                    $id = $returnReceiptType[0]->getReturnReceiptType()->getId();
                    if ($id == 0) {
                        ++$alertNbDocumentWithoutReturnReceipt;
                    }
                } else {
                    ++$alertNbDocumentWithoutReturnReceipt;
                }

                // Récupération du premier enseignant qui a commenté ce document
                $userComments = $this->getDoctrine()->getRepository('InnovaCollecticielBundle:Comment')
                ->teacherCommentDocArray($document);
                // Traitement du tableau
                $foundAdminComment = false;
                for ($indice = 0; $indice < count($userComments); ++$indice) {
                    $ResourceNode = $dropzone->getResourceNode();
                    $workspace = $ResourceNode->getWorkspace();
                    // getting the  Manager role
                    $this->role_manager = $this->get('claroline.manager.role_manager');
                    $role = $this->role_manager->getWorkspaceRolesForUser($userComments[$indice]->getUser(), $workspace);

                    // Traitement du tableau
                    for ($indiceRole = 0; $indiceRole < count($role); ++$indiceRole) {
                        $roleName = $role[$indiceRole]->getName();
                        if (strpos('_'.$roleName, 'ROLE_WS_MANAGER') === 1) {
                            if ($foundAdminComment == false) {
                                $teacherCommentDocArray[$document->getId()] =
                                $userComments[$indice]->getUser()->getFirstName().
                                ' '.$userComments[$indice]->getUser()->getLastName();
                                $foundAdminComment = true;
                            }
                        }
                    }
                }
            }
        }

        //
        // Fin partie pour calculer les compteurs
        //

        $dataToView = $this->addDropsStats($dropzone, array(
            'workspace' => $dropzone->getResourceNode()->getWorkspace(),
            '_resource' => $dropzone,
            'dropzone' => $dropzone,
            'unterminated_drops' => $countUnterminatedDrops,
            'pager' => $pager,
            'nbCommentNotRead' => $userToCommentCount,
            'userNbTextToRead' => $userNbTextToRead,
            'adminInnova' => $adminInnova,
            'collecticielOpenOrNot' => $collecticielOpenOrNot,
            'haveReturnReceiptOrNotArray' => $haveReturnReceiptOrNotArray,
            'alertNbDocumentWithoutReturnReceipt' => $alertNbDocumentWithoutReturnReceipt,
            'haveCommentOrNotArray' => $haveCommentOrNotArray,
            'teacherCommentDocArray' => $teacherCommentDocArray,
        ));

        return $dataToView;
    }

    /**
     * @Route(
     *      "/{resourceId}/drops/delete/{dropId}/{tab}/{page}",
     *      name="innova_collecticiel_drops_delete",
     *      requirements={"resourceId" = "\d+", "dropId" = "\d+", "tab" = "\d+", "page" = "\d+"},
     *      defaults={"page" = 1}
     * )
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @ParamConverter("drop", class="InnovaCollecticielBundle:Drop", options={"id" = "dropId"})
     * @Template()
     */
    public function dropsDeleteAction($dropzone, $drop, $tab, $page)
    {
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);

        $form = $this->createForm(new DropType(), $drop);

        $previousPath = 'innova_collecticiel_drops_by_user_paginated';
        if ($tab == 1) {
            $previousPath = 'innova_collecticiel_drops_by_date_paginated';
        } elseif ($tab == 2) {
            $previousPath = 'innova_collecticiel_drops_awaiting_paginated';
        }

        if ($this->getRequest()->isMethod('POST')) {
            $form->handleRequest($this->getRequest());
            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->remove($drop);
                $em->flush();

                return $this->redirect(
                    $this->generateUrl(
                        $previousPath,
                        array(
                            'resourceId' => $dropzone->getId(),
                            'page' => $page,
                        )
                    )

                );
            }
        }

        $view = 'InnovaCollecticielBundle:Drop:dropsDelete.html.twig';
        if ($this->getRequest()->isXmlHttpRequest()) {
            $view = 'InnovaCollecticielBundle:Drop:dropsDeleteModal.html.twig';
        }

        return $this->render($view, array(
            'workspace' => $dropzone->getResourceNode()->getWorkspace(),
            '_resource' => $dropzone,
            'dropzone' => $dropzone,
            'drop' => $drop,
            'form' => $form->createView(),
            'previousPath' => $previousPath,
            'tab' => $tab,
            'page' => $page,
        ));
    }

    /**
     * @Route(
     *      "/{resourceId}/drops/detail/{dropId}",
     *      name="innova_collecticiel_drops_detail",
     *      requirements={"resourceId" = "\d+", "dropId" = "\d+"}
     * )
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @Template()
     */
    public function dropsDetailAction($dropzone, $dropId)
    {
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);

        $dropResult = $this
            ->getDoctrine()
            ->getRepository('InnovaCollecticielBundle:Drop')
            ->getDropAndCorrectionsAndDocumentsAndUser($dropzone, $dropId);

        $drop = null;
        $return = $this->redirect(
            $this->generateUrl(
                'innova_collecticiel_drops_awaiting',
                array(
                    'resourceId' => $dropzone->getId(),
                )
            ));

        if (count($dropResult) > 0) {
            $drop = $dropResult[0];
            $return = array(
                'workspace' => $dropzone->getResourceNode()->getWorkspace(),
                '_resource' => $dropzone,
                'dropzone' => $dropzone,
                'drop' => $drop,
                'isAllowedToEdit' => true,
            );
        }

        return $return;
    }

    /**
     * @Route(
     *      "/{resourceId}/drop/detail/{dropId}",
     *      name="innova_collecticiel_drop_detail_by_user",
     *      requirements={"resourceId" = "\d+", "dropId" = "\d+"}
     * )
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @ParamConverter("drop", class="InnovaCollecticielBundle:Drop", options={"id" = "dropId"})
     * @Template()
     */
    public function dropDetailAction(Dropzone $dropzone, Drop $drop)
    {
        // check  if the User is allowed to open the dropZone.
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        // getting the userId to check if the current drop owner match with the loggued user.
        $userId = $this->get('security.context')->getToken()->getUser()->getId();
        $collection = new ResourceCollection(array($dropzone->getResourceNode()));
        $isAllowedToEdit = $this->get('security.context')->isGranted('EDIT', $collection);

        // getting the data
        $dropSecure = $this->getDoctrine()
            ->getRepository('InnovaCollecticielBundle:Drop')
            ->getDropAndValidEndedCorrectionsAndDocumentsByUser($dropzone, $drop->getId(), $userId);

        // if there is no result ( user is not the owner, or the drop has not ended Corrections , show 404)
        if (count($dropSecure) == 0) {
            if ($drop->getUser()->getId() != $userId) {
                throw new AccessDeniedException();
            }
        } else {
            $drop = $dropSecure[0];
        }

        $showCorrections = false;

        // if drop is complete and corrections needed were made  and dropzone.showCorrection is true.
        $user = $drop->getUser();
        $em = $this->getDoctrine()->getManager();
        $nbCorrections = $em
            ->getRepository('InnovaCollecticielBundle:Correction')
            ->countFinished($dropzone, $user);

        if ($dropzone->getDiplayCorrectionsToLearners()
        && $drop->countFinishedCorrections() >= $dropzone->getExpectedTotalCorrection()
        && $dropzone->getExpectedTotalCorrection() <= $nbCorrections
        || ($dropzone->isFinished()
        && $dropzone->getDiplayCorrectionsToLearners()
        || $drop->getUnlockedUser())
        ) {
            $showCorrections = true;
        }

        return array(
            'workspace' => $dropzone->getResourceNode()->getWorkspace(),
            '_resource' => $dropzone,
            'dropzone' => $dropzone,
            'drop' => $drop,
            'isAllowedToEdit' => $isAllowedToEdit,
            'showCorrections' => $showCorrections,
        );
    }

    /**
     * @param Drop $drop
     * @param User $user
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @Route(
     *                                                            "/unlock/drop/{dropId}",
     *                                                            name="innova_collecticiel_unlock_drop",
     *                                                            requirements={"resourceId" = "\d+", "dropId" = "\d+"}
     *                                                            )
     * @ParamConverter("drop", class="InnovaCollecticielBundle:Drop", options={"id" = "dropId"})
     * @ParamConverter("user", options={
     *                                                            "authenticatedUser" = true,
     *                                                            "messageEnabled" = true,
     *                                                            "messageTranslationKey" = "This action requires authentication. Please login.",
     *                                                            "messageTranslationDomain" = "innova_collecticiel"
     *                                                            })
     * @Template()
     */
    public function unlockDropAction(Drop $drop, User $user)
    {
        $em = $this->getDoctrine()->getManager();
        $drop->setUnlockedDrop(true);
        $em->flush();

        $this->getRequest()
            ->getSession()
            ->getFlashBag()
            ->add('success', $this->get('translator')->trans('Drop have been unlocked', array(), 'innova_collecticiel')
            );

        $dropzoneId = $drop->getDropzone()->getId();

        return $this->redirect(
            $this->generateUrl(
                'innova_collecticiel_drops_awaiting',
                array(
                    'resourceId' => $dropzoneId,
                )
            )
        );
    }

    /**
     * @Route(
     *      "/report/drop/{correctionId}",
     *      name="innova_collecticiel_report_drop",
     *      requirements={"resourceId" = "\d+", "dropId" = "\d+", "correctionId" = "\d+"}
     * )
     * @ParamConverter("correction", class="InnovaCollecticielBundle:Correction", options={"id" = "correctionId"})
     * @ParamConverter("user", options={
     *      "authenticatedUser" = true,
     *      "messageEnabled" = true,
     *      "messageTranslationKey" = "Participate in an evaluation requires authentication. Please login.",
     *      "messageTranslationDomain" = "innova_collecticiel"
     * })
     * @Template()
     */
    public function reportDropAction(Correction $correction, User $user)
    {
        $dropzone = $correction->getDropzone();
        $drop = $correction->getDrop();
        $em = $this->getDoctrine()->getManager();
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);

        try {
            $curent_user_correction = $em->getRepository('InnovaCollecticielBundle:Correction')->getNotFinished($dropzone, $user);
        } catch (NotFoundHttpException $e) {
            throw new AccessDeniedException();
        }

        if ($curent_user_correction == null || $curent_user_correction->getId() != $correction->getId()) {
            throw new AccessDeniedException();
        }
        $form = $this->createForm(new CorrectionReportType(), $correction);

        if ($this->getRequest()->isMethod('POST')) {
            $form->handleRequest($this->getRequest());
            if ($form->isValid()) {
                $drop->setReported(true);
                $correction->setReporter(true);
                $correction->setEndDate(new \DateTime());
                $correction->setFinished(true);
                $correction->setTotalGrade(0);

                $em->persist($drop);
                $em->persist($correction);
                $em->flush();

                $this->dispatchDropReportEvent($dropzone, $drop, $correction);
                $this
                    ->getRequest()
                    ->getSession()
                    ->getFlashBag()
                    ->add('success', $this->get('translator')->trans('Your report has been saved', array(), 'innova_collecticiel'));

                return $this->redirect(
                    $this->generateUrl(
                        'innova_collecticiel_open',
                        array(
                            'resourceId' => $dropzone->getId(),
                        )
                    )
                );
            }
        }

        $view = 'InnovaCollecticielBundle:Drop:reportDrop.html.twig';
        if ($this->getRequest()->isXmlHttpRequest()) {
            $view = 'InnovaCollecticielBundle:Drop:reportDropModal.html.twig';
        }

        return $this->render($view, array(
            'workspace' => $dropzone->getResourceNode()->getWorkspace(),
            '_resource' => $dropzone,
            'dropzone' => $dropzone,
            'drop' => $drop,
            'correction' => $correction,
            'form' => $form->createView(),
        ));
    }

    protected function dispatchDropReportEvent(Dropzone $dropzone, Drop $drop, Correction $correction)
    {
        $rm = $this->get('claroline.manager.role_manager');
        $event = new LogDropReportEvent($dropzone, $drop, $correction, $rm);
        $this->get('event_dispatcher')->dispatch('log', $event);
    }

    /**
     * @Route(
     *      "/{resourceId}/remove/report/{dropId}/{correctionId}/{invalidate}",
     *      name="innova_collecticiel_remove_report",
     *      requirements={"resourceId" = "\d+", "dropId" = "\d+", "correctionId" = "\d+", "invalidate" = "0|1"}
     * )
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @ParamConverter("drop", class="InnovaCollecticielBundle:Drop", options={"id" = "dropId"})
     * @ParamConverter("correction", class="InnovaCollecticielBundle:Correction", options={"id" = "correctionId"})
     * @Template()
     */
    public function removeReportAction(Dropzone $dropzone, Drop $drop, Correction $correction, $invalidate)
    {
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);

        $em = $this->getDoctrine()->getManager();
        $correction->setReporter(false);

        if ($invalidate == 1) {
            $correction->setValid(false);
        }

        $em->persist($correction);
        $em->flush();

        $correctionRepo = $this->getDoctrine()->getRepository('InnovaCollecticielBundle:Correction');
        if ($correctionRepo->countReporter($dropzone, $drop) == 0) {
            $drop->setReported(false);
            $em->persist($drop);
            $em->flush();
        }

        $event = new LogCorrectionUpdateEvent($dropzone, $drop, $correction);
        $this->dispatch($event);

        return $this->redirect(
            $this->generateUrl(
                'innova_collecticiel_drops_detail',
                array(
                    'resourceId' => $dropzone->getId(),
                    'dropId' => $drop->getId(),
                )
            )
        );
    }

    /**
     * @Route(
     *      "/{resourceId}/autoclosedrops/confirm",
     *      name="innova_collecticiel_auto_close_drops_confirmation",
     *      requirements={"resourceId" = "\d+", "dropId" = "\d+"}
     * )
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @Template()
     */
    public function autoCloseDropsConfirmationAction($dropzone)
    {
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);

        $view = 'InnovaCollecticielBundle:Dropzone:confirmCloseUnterminatedDrop.html.twig';
        if ($this->getRequest()->isXmlHttpRequest()) {
            $view = 'InnovaCollecticielBundle:Dropzone:confirmCloseUnterminatedDropModal.html.twig';
        }

        return $this->render($view, array(
            'workspace' => $dropzone->getResourceNode()->getWorkspace(),
            '_resource' => $dropzone,
            'dropzone' => $dropzone,
        ));
    }

    /**
     * @Route(
     *      "/{resourceId}/autoclosedrops",
     *      name="innova_collecticiel_auto_close_drops",
     *      requirements={"resourceId" = "\d+"}
     * )
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     */
    public function autoCloseDropsAction($dropzone)
    {
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);

        $dropzoneManager = $this->get('innova.manager.dropzone_manager');
        $dropzoneManager->closeDropzoneOpenedDrops($dropzone, true);

        return $this->redirect(
            $this->generateUrl(
                'innova_collecticiel_drops_awaiting',
                array(
                    'resourceId' => $dropzone->getId(),
                )
            )
        );
    }

    /**
     * @Route(
     *      "/{resourceId}/shared/spaces",
     *      name="innova_collecticiel_shared_spaces",
     *      requirements={"resourceId" = "\d+"},
     *      defaults={"page" = 1}
     * )
     * @Route(
     *      "/{resourceId}/shared/spaces/{page}",
     *      name="innova_collecticiel_shared_spaces_paginated",
     *      requirements={"resourceId" = "\d+", "page" = "\d+"},
     *      defaults={"page" = 1}
     * )
     * @ParamConverter("dropzone", class="InnovaCollecticielBundle:Dropzone", options={"id" = "resourceId"})
     * @Template()
     */
    public function sharedSpacesAction($dropzone, $page)
    {

// Onglet "Espaces partagés"
        $this->get('innova.manager.dropzone_voter')->isAllowToOpen($dropzone);
        $this->get('innova.manager.dropzone_voter')->isAllowToEdit($dropzone);
        $dropzoneManager = $this->get('innova.manager.dropzone_manager');

        $dropzoneVoter = $this->get('innova.manager.dropzone_voter');

        // Récupération du Workspace
        $workspace = $dropzone->getResourceNode()->getWorkspace();

        $dropRepo = $this->getDoctrine()->getManager()->getRepository('InnovaCollecticielBundle:Drop');

        // Ajout du code pour afficher les élèves inscrits mais qui n'ont pas déposé. InnovaERV.
        // Déclaration du tableau de workspace
        $workspaceArray = array();

        // Récupération du workspace courant
        $workspaceId = $dropzone->getResourceNode()->getWorkspace()->getId();
        $workspaceArray[] = $workspaceId;

        $userManager = $this->get('claroline.manager.user_manager');
        $withPager = false;
        $usersByWorkspaces = $userManager->getUsersByWorkspaces($workspaceArray, $page, 20, $withPager);
//      var_dump($usersByWorkspaces[0]);

        $userWithRights = $userManager->getUsersWithRights($dropzone->getResourceNode());
        // Fin ajout du code pour afficher les élèves inscrits mais qui n'ont pas déposé. InnovaERV.

        // dropsQuery : finished à TRUE et unlocked_drop à FALSE
        $dropsQuery = $dropRepo->getSharedSpacesQuery($dropzone, $workspace);

        $countUnterminatedDrops = $dropRepo->countUnterminatedDropsByDropzone($dropzone->getId());

        // Déclarations des nouveaux tableaux, qui seront passés à la vue
        $userNbDocDropped = array();
        $userNbAdressedRequests = array();

        foreach ($dropzone->getDrops() as $drop) {
            /* InnovaERV : ajout pour calculer les 2 zones **/

            // Nombre de documents déposés/ Repo : Document
            $nbDocDropped = $this->getDoctrine()
                                ->getRepository('InnovaCollecticielBundle:Document')
                                ->countDocSubmissions($drop->getUser(), $drop->getDropZone());

            // Nombre de demandes adressées/ Repo : Document
            $nbAdressedRequests = $this->getDoctrine()
                                ->getRepository('InnovaCollecticielBundle:Document')
                                ->countTextToRead($drop->getUser(), $drop->getDropZone());

            // Affectations des résultats dans les tableaux
            $userNbDocDropped[$drop->getUser()->getId()] = $nbDocDropped;
            $userNbAdressedRequests[$drop->getUser()->getId()] = $nbAdressedRequests;
        }

        $adapter = new DoctrineORMAdapter($dropsQuery);
        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage(DropzoneBaseController::DROP_PER_PAGE);

        //echo DropzoneBaseController::DROP_PER_PAGE . "--";
        try {
            $pager->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            if ($page > 0) {
                return $this->redirect(
                    $this->generateUrl(
                        'innova_collecticiel_shared_spaces_paginated',
                        array(
                            'resourceId' => $dropzone->getId(),
                            'page' => $pager->getNbPages(),
                        )
                    )
                );
            } else {
                throw new NotFoundHttpException();
            }
        }

        $adminInnova = $dropzoneVoter->checkEditRight($dropzone);

        $collecticielOpenOrNot = $dropzoneManager->collecticielOpenOrNot($dropzone);

        /*
        if ($this->get('security.context')->isGranted('ROLE_ADMIN' === true)) {
            $adminInnova = true;
        }*/

        $dataToView = $this->addDropsStats($dropzone, array(
            'workspace' => $dropzone->getResourceNode()->getWorkspace(),
            '_resource' => $dropzone,
            'dropzone' => $dropzone,
            'unterminated_drops' => $countUnterminatedDrops,
            'pager' => $pager,
            'userNbDocDropped' => $userNbDocDropped,
            'userNbAdressedRequests' => $userNbAdressedRequests,
            'adminInnova' => $adminInnova,
            'usersByWorkspaces' => $usersByWorkspaces,
            'collecticielOpenOrNot' => $collecticielOpenOrNot,
        ));

        return $dataToView;
    }

    /**
     * @Route(
     *      "/drop/reception",
     *      name="innova_collecticiel_return_receipt",
     *      options={"expose"=true}
     * )
     * @Template()
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function returnReceiptAction()
    {

        // Récupération de l'ID de l'accusé de réception choisi
        $returnReceiptId = $this->get('request')->query->get('returnReceiptId');
        $returnReceiptType =
        $this->getDoctrine()->getRepository('InnovaCollecticielBundle:ReturnReceiptType')->find($returnReceiptId);

        // Récupération de l'ID du dropzone choisi
        $dropzoneId = $this->get('request')->query->get('dropzoneId');
        $dropzone = $this->getDoctrine()->getRepository('InnovaCollecticielBundle:Dropzone')->find($dropzoneId);

        // Récupération des documents sélectionnés
        $arrayDocsId = $this->get('request')->query->get('arrayDocsId');

        $em = $this->getDoctrine()->getManager();

        // Récupération de l'utilisateur
        $user = $this->get('security.token_storage')->getToken()->getUser();

        // Parcours des documents sélectionnés et insertion en base de données
        foreach ($arrayDocsId as $documentId) {
            // Par le JS, le document est transmis sous la forme "document_id_XX"
            $docIdS = explode('_', $documentId);
            $docId = $docIdS[2];

            if ($docId > 0) {

                // Récupération de l'objet document
                $document = $this->getDoctrine()
                ->getRepository('InnovaCollecticielBundle:Document')->find($docId);

                // Nombre de demandes adressées/ Repo : Document
                $countHaveReturnReceiptOrNotForADocument = $this->getDoctrine()
                                    ->getRepository('InnovaCollecticielBundle:ReturnReceipt')
                                    ->haveReturnReceiptOrNotForADocument($user, $dropzone, $document);

                // S'il y a déjà un accusé de réception alors je le supprime avant de créer le nouveau
                if ($countHaveReturnReceiptOrNotForADocument != 0) {
                    // Nombre de demandes adressées/ Repo : Document
                    $reqDeleteReturnReceipt = $this->getDoctrine()
                                        ->getRepository('InnovaCollecticielBundle:ReturnReceipt')
                                        ->deleteReturnReceipt($user, $dropzone, $document);
                }

                // Création du nouvel accusé de réception
                $returnReceipt = new ReturnReceipt();
                $returnReceipt->setDocument($document);
                $returnReceipt->setUser($user);
                $returnReceipt->setDropzone($dropzone);
                $returnReceipt->setReturnReceiptType($returnReceiptType);

                $em->persist($returnReceipt);

                // Envoi notification. InnovaERV
                $usersIds = array();

                // Ici, on récupère le créateur du collecticiel = l'admin
                $userCreator = $dropzone->getResourceNode()->getCreator()->getId();
                // Ici, on récupère celui qui vient de déposer le nouveau document
                //$userAddDocument = $this->get('security.context')->getToken()->getUser()->getId(); 
                $userDropDocument = $document->getDrop()->getUser()->getId();
                $userSenderDocument = $document->getSender()->getId();

                // Ici avertir l'étudiant qui a travaillé sur ce collecticiel
                $usersIds[] = $userDropDocument;

                //$event = new LogDropzoneValidateDocumentEvent($document, $dropzone, $usersIds);
                $event = new LogDropzoneReturnReceiptEvent($document, $dropzone, $usersIds);

                $this->get('event_dispatcher')->dispatch('log', $event);
                // Fin de l'ajout de la notification
            }
        }

        // Mise en base, enregistrement
        $em->flush();

        $dropzoneManager = $this->get('innova.manager.dropzone_manager');
        $dropzoneVoter = $this->get('innova.manager.dropzone_voter');
        $em = $this->getDoctrine()->getManager();
        $flashbag = $this->getRequest()->getSession()->getFlashBag();

        // on teste si l'utilisateur à le droit d'ouvrir le dropzone
        $dropzoneVoter->isAllowToOpen($dropzone);

        $canEdit = $dropzoneVoter->checkEditRight($dropzone);

        $activeRoute = $this->getRequest()->attributes->get('_route');

        $collecticielOpenOrNot = $dropzoneManager->collecticielOpenOrNot($dropzone);

        $redirectRoot = $this->generateUrl(
            'innova_collecticiel_drops_awaiting',
            array(
                    'resourceId' => $dropzone->getId(),
                )
            );

        return new JsonResponse(
            array(
                'link' => $redirectRoot,
            )
        );
    }

    /**
     * @Route(
     *      "/back/link",
     *      name="innova_collecticiel_back_link",
     *      options={"expose"=true}
     * )
     * @Template()
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function backLinkAction()
    {

        // Récupération de l'ID du dropzone choisi
        $dropzoneId = $this->get('request')->query->get('dropzoneId');
        $dropzone = $this->getDoctrine()->getRepository('InnovaCollecticielBundle:Dropzone')->find($dropzoneId);

        $redirectRoot = $this->generateUrl(
            'innova_collecticiel_drops_awaiting',
            array(
                    'resourceId' => $dropzone->getId(),
                )
            );

        return new JsonResponse(
            array(
                'link' => $redirectRoot,
            )
        );
    }
}
