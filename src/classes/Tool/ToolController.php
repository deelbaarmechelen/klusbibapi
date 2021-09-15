<?php

namespace Api\Tool;

use Api\Exception\ForbiddenException;
use Api\Exception\NotFoundException;
use Api\Exception\NotImplementedException;
use Api\Inventory\Inventory;
use Api\Product\ProductControllerInterface;
use Api\Util\HttpResponseCode;
use Illuminate\Database\Capsule\Manager as Capsule;
use Api\ModelMapper\ToolMapper;
use Api\Model\Tool;
use Api\ModelMapper\ReservationMapper;
use Api\Authorisation;
use Api\Upload\UploadHandler;

class ToolController implements ProductControllerInterface
{
    protected $logger;
    protected $toolManager;
    protected $token;

    public function __construct($logger, ToolManager $toolManager, $token) {
        $this->logger = $logger;
        $this->toolManager = $toolManager;
        $this->token = $token;
    }

    function getAll($request, $response, $args) {

        $this->logger->info("Klusbib GET '/tools' route");
        $sortdir = $request->getQueryParam('_sortDir');
        if (!isset($sortdir)) {
            $sortdir = 'asc';
        }
        $sortfield = $request->getQueryParam('_sortField');
        if (!Tool::canBeSortedOn($sortfield) ) {
            $sortfield = 'code';
        }
        $page = $request->getQueryParam('_page');
        if (!isset($page)) {
            $page = '1';
        }
        $perPage = $request->getQueryParam('_perPage');
        if (!isset($perPage)) {
            $perPage = '1000';
        }
        $showAll = $request->getQueryParam('_all');
        if (isset($showAll) && $showAll == 'true') {
            $showAll = true;
        } else {
            $showAll = false;
        }
        $query = $request->getQueryParam('_query');
        $category = $request->getQueryParam('category');
        $tools = $this->toolManager->getAll($showAll, $category, $sortfield, $sortdir, $page, $perPage, $query);

        // TODO: if not admin, filter non visible tools
    //    if ($showAll === true) {
    //        $builder = Capsule::table('tools');
    //    } else {
    //        $builder = Capsule::table('tools')
    //            ->where('visible', true);
    //    }
    //
    //    if (isset($category)) {
    //        $builder = $builder->where('category', $category);
    //    }
    //    $tools = $builder->orderBy($sortfield, $sortdir)->get();
        $tools_page = array_slice($tools->all(), ($page - 1) * $perPage, $perPage);

        $data = array();
        foreach ($tools_page as $tool) {
            array_push($data, ToolMapper::mapToolToArray($tool));
        }
        return $response->withJson($data)
            ->withHeader('X-Total-Count', count($tools));
    }

    function getById($request, $response, $args) {
        $this->logger->info("Klusbib GET '/tools/id' route");

        try {
            $tool = $this->toolManager->getById($args['toolid']);
        } catch (NotFoundException $nfe) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }

        if (null == $tool) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }

        $data = ToolMapper::mapToolToArray($tool);

        // Add tool reservations
        $reservations = $tool->reservations;
        $reservationsArray = array();
        foreach ($reservations as $reservation) {
            array_push($reservationsArray, ReservationMapper::mapReservationToArray($reservation));
        }
        $data["reservations"] = $reservationsArray;
        return $response->withJson($data);
    }
    function create($request, $response, $args) {
        $this->logger->info("Klusbib POST '/tools' route");
        /* Check if token has needed scope. */
        try {
            Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.create"]);
        } catch (ForbiddenException $e) {
            return $response->withStatus(HttpResponseCode::FORBIDDEN)->withJson(array("error" => $e->getMessage()));
        }

        $data = $request->getParsedBody();
        if (empty($data) || empty($data["name"])) {
            return $response->withStatus(HttpResponseCode::BAD_REQUEST); // Bad request
        }
        $tool = new Tool();
        // 	$tool->name = filter_var($data['name'], FILTER_SANITIZE_STRING);
        ToolMapper::mapArrayToTool($data, $tool);
        $tool->save();
        return $response->withJson(ToolMapper::mapToolToArray($tool));
    }
    function uploadProductImage($request, $response, $args) {
        $this->logger->info("Klusbib POST '/tools/{toolid}/upload' route");
        /* Check if token has needed scope. */
        Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.update"]);

        $this->logger->info('$_FILES=' . json_encode($_FILES));
        $files = $request->getUploadedFiles();
        $this->logger->info('$files=' . json_encode($files));

        $tool = Tool::find($args['toolid']);
        if (null == $tool) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
        // upload file and save location to tool img url
        $uploader = new UploadHandler($this->logger);
        if (empty($tool->code) || $tool->code == 'not assigned') {
            $uploader->uploadFile($files['newfile']);
        } else {
            $uploader->uploadFile($files['newfile'], $tool->code);
        }

        $tool->img = $uploader->getUploadPublicUrl();
        $tool->save();
        return $response->withJson(ToolMapper::mapToolToArray($tool));
    }

    function update($request, $response, $args) {
        $this->logger->info("Klusbib PUT '/tools/id' route");
        Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.update"]);
        $tool = Tool::find($args['toolid']);
        if (null == $tool) {
            return $response->withStatus(HttpResponseCode::NOT_FOUND);
        }
        $data = $request->getParsedBody();
        ToolMapper::mapArrayToTool($data, $tool);
        $tool->save();
        return $response->withJson(ToolMapper::mapToolToArray($tool));
    }

    function delete($request, $response, $args) {
        $this->logger->info("Klusbib DELETE '/tools/id' route");
        Authorisation::checkAccessByToken($this->token, ["tools.all", "tools.delete"]);
    // 	if (false === $this->token->hasScope(["tools.all", "tools.delete"])) {
    // 		throw new ForbiddenException("Token not allowed to delete tools.", 403);
    // 	}
        $tool = \Api\Model\Tool::find($args['toolid']);
        if (null == $tool) {
            return $response->withStatus(HttpResponseCode::NO_CONTENT);
        }
        $tool->delete();
        return $response->withStatus(HttpResponseCode::OK);
    }

    function add($request, $response, $args)
    {
        // TODO: Implement getDisabledDates() method.
        throw new NotImplementedException("Not supported in this version. Did you intend to use create()?");
    }

    /**
     * @deprecated
     * Use delete($request, $response, $args) instead
     */
    function deleteByID($request, $response, $args)
    {
        trigger_error('Method ' . __METHOD__ . ' is deprecated', E_USER_DEPRECATED);
        return $this->delete($request, $response, $args);
    }

    function getDisabledDates($request, $response, $args)
    {
        // TODO: Implement getDisabledDates() method.
        throw new NotImplementedException("Not supported in this version");
    }
}