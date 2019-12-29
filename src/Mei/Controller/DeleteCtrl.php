<?php declare(strict_types=1);

namespace Mei\Controller;

use Exception;
use Slim\Http\Request;
use Slim\Http\Response;
use Tracy\Debugger;

/**
 * Class DeleteCtrl
 *
 * @package Mei\Controller
 */
class DeleteCtrl extends BaseCtrl
{
    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     *
     * @return Response
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        // dont abort if client disconnects
        ignore_user_abort(true);

        $auth = $request->getParam('auth');

        if (!hash_equals($auth, $this->config['api.auth_key'])) {
            return $response->withJson(['success' => false, 'reason' => 'access denied'])->withStatus(403);
        }

        $imgs = json_decode($request->getParam('imgs'));
        $success = count($imgs);

        if (!$success) {
            return $response->withJson(['success' => false, 'reason' => 'imgs array was empty'])->withStatus(200);
        }

        foreach ($imgs as $img) {
            $info = pathinfo($img);
            $fileEntity = $this->di['model.files_map']->getByFileName($info['filename'] . '.' . $info['extension']);

            if (!$fileEntity) {
                $success--;
                continue;
            }
            if ($fileEntity->Protected) {
                $fileEntity->Protected--;
                $this->di['model.files_map']->save($fileEntity); // decrease protected count by one

                $success--;
                continue;
            }

            $uri = $request->getUri();
            $domain = $uri->getScheme() . '://' . $uri->getHost();
            $urls = [
                ($domain . $this->di['router']->pathFor(
                        'serve',
                        ['img' => $info['filename'] . '.' . $info['extension']]
                    )),
                ($domain . $this->di['router']->pathFor(
                        'serve:legacy',
                        ['img' => $info['filename'] . '.' . $info['extension']]
                    ))
            ];

            foreach (ServeCtrl::$legacySizes as $resInfo) // handling common resolutions + crop
            {
                $urls[] = ($domain . $this->di['router']->pathFor(
                        'serve',
                        [
                            'img' => (
                                $info['filename'] . '-' . $resInfo[0] . 'x' . $resInfo[1] . '.' . $info['extension']
                            )
                        ]
                    ));
                $urls[] = ($domain . $this->di['router']->pathFor(
                        'serve:legacy',
                        [
                            'img' => (
                                $info['filename'] . '-' . $resInfo[0] . 'x' . $resInfo[1] . '.' . $info['extension']
                            )
                        ]
                    ));

                $urls[] = ($domain . $this->di['router']->pathFor(
                        'serve',
                        [
                            'img' => (
                                $info['filename'] . '-' . $resInfo[0] . 'x' . $resInfo[1] . '-crop.' . $info['extension']
                            )
                        ]
                    ));
                $urls[] = ($domain . $this->di['router']->pathFor(
                        'serve:legacy',
                        [
                            'img' => (
                                $info['filename'] . '-' . $resInfo[0] . 'x' . $resInfo[1] . '-crop.' . $info['extension']
                            )
                        ]
                    ));
            }

            try {
                $this->di['model.files_map']->delete($fileEntity);
                if (!$this->di['model.files_map']->getByKey(
                    $fileEntity->Key
                )) { // file does not exist anymore anywhere, remove it
                    $savePath = pathinfo($fileEntity->Key);
                    $this->di['utility.images']->deleteImage(
                        $this->di['utility.images']->getSavePath(
                            $savePath['filename'] . '.' . $this->di['utility.images']->mapExtension(
                                $savePath['extension']
                            )
                        )
                    );
                }
            } catch (Exception $e) {
                $success--;
                Debugger::log($e, Debugger::EXCEPTION);
            }

            try {
                $this->di['utility.images']->clearCacheForImage($urls);
            } catch (Exception $e) {
                Debugger::log($e, Debugger::EXCEPTION);
            }
        }

        return $response->withJson(['success' => $success])->withStatus(200);
    }
}
