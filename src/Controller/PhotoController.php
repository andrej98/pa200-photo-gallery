<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Form\PhotoType;
use App\Message\ImageToProcess;
use Doctrine\ORM\EntityManagerInterface;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class PhotoController extends AbstractController
{
    #[Route('/photos', name: 'photo_index')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $repository = $em->getRepository(Photo::class);
        $photos = $repository->findAll();

        return $this->render('photo/index.html.twig', [
            'photos' => $photos,
        ]);
    }


    #[Route('/photos/new', name: 'photo_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, MessageBusInterface $bus): Response
    {
        $photo = new Photo();
        $form = $this->createForm(PhotoType::class, $photo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            $name = $form->get('name')->getData();
            $description = $form->get('description')->getData();

            if ($file) {
                $safeFilename = $slugger->slug($name);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                // Get Azure Blob Storage credentials from environment variables
                $accountName = $_ENV['AZURE_STORAGE_ACCOUNT'];
                $accountKey = $_ENV['AZURE_STORAGE_KEY'];
                $containerName = $_ENV['AZURE_STORAGE_CONTAINER'];

                // Create Blob client
                $blobClient = BlobRestProxy::createBlobService(
                    sprintf('DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s', $accountName, $accountKey)
                );

                try {
                    // Upload blob to container
                    $content = file_get_contents($file->getPathname());
                    $blobClient->createBlockBlob($containerName, $newFilename, $content);

                    // Get URL of the uploaded file
                    $url = sprintf('https://%s.blob.core.windows.net/%s/%s', $accountName, $containerName, $newFilename);

                    // Update the photo entity
                    $photo->setName($name);
                    $photo->setDescription($description);
                    $photo->setUrl($url);
                    $em->persist($photo);
                    $em->flush();

                    $message = new ImageToProcess($photo->getId());
                    $bus->dispatch($message);

                    return $this->redirectToRoute('photo_index');
                } catch (ServiceException $e) {
                    $this->addFlash('error', 'Failed to upload image to Azure Blob Storage.');
                }
            }
        }

        return $this->render('photo/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/photos/{id}', name: 'photo_show', methods: ['GET'])]
    public function show(Photo $photo): Response
    {
        return $this->render('photo/show.html.twig', [
            'photo' => $photo,
        ]);
    }

    #[Route('/photos/{id}/delete', name: 'photo_delete', methods: ['POST'])]
    public function delete(Request $request, Photo $photo, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $photo->getId(), $request->request->get('_token'))) {
            // Delete the photo from Azure Blob Storage
            $accountName = $_ENV['AZURE_STORAGE_ACCOUNT'];
            $accountKey = $_ENV['AZURE_STORAGE_KEY'];
            $containerName = $_ENV['AZURE_STORAGE_CONTAINER'];

            $blobClient = BlobRestProxy::createBlobService(
                sprintf('DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s', $accountName, $accountKey)
            );

            try {
                $blobClient->deleteBlob($containerName, basename($photo->getUrl()));
                if($photo->getBwUrl()) {
                    $blobClient->deleteBlob($containerName, basename($photo->getBwUrl()));
                }
            } catch (ServiceException $e) {
                $this->addFlash('error', 'Failed to delete image from Azure Blob Storage.');
            }

            $em->remove($photo);
            $em->flush();

            $this->addFlash('success', 'Photo deleted successfully.');
        }

        return $this->redirectToRoute('photo_index');
    }
}
