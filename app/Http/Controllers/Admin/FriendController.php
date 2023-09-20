<?php


namespace App\Http\Controllers\Admin;

use App\Tool\Constant;
use App\Models\Friends;
use App\Models\Members;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\FriendsMessage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FriendController extends Controller
{
    /**
     * 好友列表
     * User: zmm
     * DateTime: 2023/5/30 11:34
     * @param  Request  $request
     * @return JsonResponse
     */
    public function friends(Request $request): JsonResponse
    {
        return $this->responseSuccess(['friend_list' => Friends::getContacts($request['uid'])]);
    }


    /**
     * 删除好友
     * User: zmm
     * DateTime: 2023/6/1 18:12
     */
    public function delFriends(Request $request): JsonResponse
    {
        $request->validate(['friend_id' => 'bail|required|int']);
        $friendModel = Friends::query()->where([
            'uid'       => $request['uid'],
            'friend_id' => $request['friend_id'],
        ])->firstOr(['*'], function () {
            throw new BadRequestHttpException(__("删除的好友不存在"));
        });

        DB::beginTransaction();
        $friendModel->delete();
        DB::commit();

        return $this->responseSuccess();
    }

    /**
     * 搜索好友
     * User: zmm
     * DateTime: 2023/5/30 18:32
     * @param  Request  $request
     * @return JsonResponse
     */
    public function searchFriends(Request $request): JsonResponse
    {
        $request->validate(['keywords' => 'bail|required|max:128']);

        $memberList = Members::query()->where('email', $request['keywords'])->orWhere(
            'account',
            $request['keywords']
        )->get(['nick_name', 'sign', 'avatar', 'id'])->toArray();
        // 查看好友列表是否已成为自己的好友
        foreach ($memberList as $k => $v) {
            if ($request['uid'] == $v['id']) {
                $memberList[$k]['is_friend'] = true;
            } else {
                $memberList[$k]['is_friend'] = Friends::query()->where([
                    'uid'       => $request['uid'],
                    'friend_id' => $v['id'],
                ])->exists();
            }
        }

        return $this->responseSuccess(['friend_list' => $memberList]);
    }

    /**
     * 好友聊天记录
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function messageFriends(Request $request): JsonResponse
    {
        //
        $request->validate([
            'uid'         => 'bail|required|int',
            'friend_id'         => 'bail|required|int',
            'message'         => 'bail|nullable',
        ]);

        $list = FriendsMessage::where([
            'talk_type' => 1,
            'from_uid' => $request['uid'],
            'to_uid' => $request['friend_id'],
        ])->when($request['message'], function ($query) use ($request) {
            //
            $query->where('message', 'like', "%{$request['message']}%");
        })->when($request['date'], function ($query) use ($request) {
            //
            [$startDate, $endDate] = explode(',', $request['date']);
            $query->whereBetween('created_at', [$startDate, $endDate]);
        })->orderByDesc('id')
            ->paginate(page_size(), [
                'id', 'from_uid', 'to_uid', 'is_revoke', 'quote_id', 'is_delete', 'message_type', 'message', 'created_at'
            ]);
        return $this->responseSuccess($list);
    }
}
