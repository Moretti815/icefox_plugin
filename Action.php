<?php
use Widget\Notice;
class Icefox_Action extends Typecho_Widget implements Widget_Interface_Do{
    public function action(){
        $request = Typecho_Request::getInstance();
        $user = Typecho_Widget::widget('Widget_User');

        // 操作类型
        $do = $request->get('do');
        if (empty($do)) {
            $this->returnJson(['success' => false, 'message' => '操作类型缺失']);
            return;
        }

        // 点赞和评论相关操作不需要管理员权限
        if ($do === 'like' || $do === 'getLikes' || $do === 'addComment') {
            if ($do === 'like') {
                $this->toggleLike();
            } else if ($do === 'getLikes') {
                $this->getLikes();
            } else if ($do === 'addComment') {
                $this->addComment();
            }
            return;
        }

        // 其他操作需要管理员权限
        if (!$user->pass('administrator')) {
            Notice::alloc()->set(
                '无权操作',
                'error'
            );
            return;
        }

        // 获取文章ID
        $cid = $request->get('cid');
        if (empty($cid)) {
            Notice::alloc()->set(
                '文章ID缺失',
                'error'
            );
            return;
        }

        $stat = $request->get('stat');

        // 处理更新状态
        if($stat == 1){
            $stat = 0;
        }else{
            $stat = 1;
        }

        if($do =='top'){
            // 更新置顶状态
            self::setTop($cid, $stat);
        }else if($do == 'recomment'){
            self::setRecomment($cid, $stat);
        }

        // 设置成功提示并重定向
        Notice::alloc()->set(
            '操作成功',
            'success'
        );
        $this->response->redirect(Typecho_Common::url('admin/manage-posts.php', null));
    }

    /**
     * 设置置顶状态
     */
    public function setTop($cid, $stat){
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        $sql = " INSERT INTO `{$prefix}icefox_archive` (cid, is_top, likes)
 VALUES ({$cid}, $stat, 0)
 ON DUPLICATE KEY UPDATE is_top = VALUES(is_top);";

        return $db->fetchRow($db->query($sql));
    }

    /**
     * 切换点赞状态（点赞/取消点赞）
     */
    private function toggleLike() {
        $request = Typecho_Request::getInstance();
        $cid = $request->get('cid');

        if (empty($cid)) {
            $this->returnJson(['success' => false, 'message' => '文章ID缺失']);
            return;
        }

        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $user = Typecho_Widget::widget('Widget_User');

        // 获取用户信息
        $uid = $user->hasLogin() ? $user->uid : null;
        $ip = $this->request->getIp();
        $anonymousId = $request->get('anonymous_id');
        $currentTime = time();
        $author = null;
        $mail = null;

        // 如果用户已登录，获取用户信息
        if ($uid) {
            $author = $user->screenName;
            $mail = $user->mail;
        } else {
            // 未登录用户，尝试从评论记录中查找用户信息
            $userInfo = $this->getUserInfoFromComments($ip);
            if ($userInfo) {
                $author = $userInfo['author'];
                $mail = $userInfo['mail'];
            }
        }

        try {
            // 检查用户是否已经点赞
            $query = $db->select()->from('table.icefox_likes')->where('cid = ?', $cid);

            if ($uid) {
                // 登录用户：通过 uid 识别
                $query->where('uid = ?', $uid);
            } elseif ($mail) {
                // 评论过的用户：通过 mail 识别
                $query->where('mail = ?', $mail);
            } elseif ($anonymousId) {
                // 完全匿名用户：通过 anonymous_id 识别
                $query->where('anonymous_id = ?', $anonymousId);
            } else {
                // 降级方案：通过 IP 识别（不推荐）
                $query->where('ip = ?', $ip)->where('mail IS NULL')->where('anonymous_id IS NULL');
            }

            $liked = $db->fetchRow($query);

            if ($liked) {
                // 已点赞，执行取消点赞
                $deleteQuery = $db->delete('table.icefox_likes')->where('cid = ?', $cid);

                if ($uid) {
                    $deleteQuery->where('uid = ?', $uid);
                } elseif ($mail) {
                    $deleteQuery->where('mail = ?', $mail);
                } elseif ($anonymousId) {
                    $deleteQuery->where('anonymous_id = ?', $anonymousId);
                } else {
                    $deleteQuery->where('ip = ?', $ip)->where('mail IS NULL')->where('anonymous_id IS NULL');
                }

                $db->query($deleteQuery);

                // 减少点赞数
                $db->query("UPDATE `{$prefix}icefox_archive` SET likes = GREATEST(likes - 1, 0) WHERE cid = {$cid}");

                $isLiked = false;
                $message = '取消点赞成功';
            } else {
                // 未点赞，执行点赞
                $data = [
                    'cid' => $cid,
                    'uid' => $uid,
                    'author' => $author,
                    'mail' => $mail,
                    'ip' => $ip,
                    'anonymous_id' => $anonymousId,
                    'created_at' => $currentTime
                ];
                $db->query($db->insert('table.icefox_likes')->rows($data));

                // 增加点赞数，如果记录不存在则创建
                $sql = "INSERT INTO `{$prefix}icefox_archive` (cid, is_top, likes)
                        VALUES ({$cid}, 0, 1)
                        ON DUPLICATE KEY UPDATE likes = likes + 1";
                $db->query($sql);

                $isLiked = true;
                $message = '点赞成功';
            }

            // 获取最新点赞数和点赞用户列表
            $archive = $db->fetchRow($db->select('likes')->from('table.icefox_archive')->where('cid = ?', $cid));
            $likes = $archive ? $archive['likes'] : 0;

            // 获取点赞用户列表
            $likeUsers = $this->getLikeUsers($cid);

            $this->returnJson([
                'success' => true,
                'message' => $message,
                'isLiked' => $isLiked,
                'likes' => $likes,
                'likeUsers' => $likeUsers
            ]);
        } catch (Exception $e) {
            $this->returnJson(['success' => false, 'message' => '操作失败：' . $e->getMessage()]);
        }
    }

    /**
     * 从评论记录中查找用户信息
     */
    private function getUserInfoFromComments($ip) {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        // 验证并转义IP地址防止SQL注入
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
        if (empty($ip)) {
            return null;
        }

        // 使用 addslashes 转义（适用于 MySQL）
        $ip = addslashes($ip);

        // 通过IP查找最近的评论记录，使用原生SQL避免查询构造器的问题
        $sql = "SELECT `author`, `mail` FROM `{$prefix}comments`
                WHERE `ip` = '{$ip}'
                AND `mail` IS NOT NULL
                AND `mail` != ''
                ORDER BY `created` DESC
                LIMIT 1";

        $comment = $db->fetchRow($sql);

        return $comment;
    }

    /**
     * 获取点赞用户列表
     */
    private function getLikeUsers($cid) {
        $db = Typecho_Db::get();

        $likes = $db->fetchAll(
            $db->select('author', 'mail', 'created_at')
                ->from('table.icefox_likes')
                ->where('cid = ?', $cid)
                ->order('created_at', Typecho_Db::SORT_DESC)
        );

        $users = [];
        foreach ($likes as $like) {
            if (!empty($like['author'])) {
                $users[] = [
                    'author' => $like['author'],
                    'mail' => $like['mail']
                ];
            }
        }

        return $users;
    }

    /**
     * 获取点赞信息（点赞数和当前用户是否已点赞）
     */
    private function getLikes() {
        $request = Typecho_Request::getInstance();
        $cid = $request->get('cid');

        if (empty($cid)) {
            $this->returnJson(['success' => false, 'message' => '文章ID缺失']);
            return;
        }

        $db = Typecho_Db::get();
        $user = Typecho_Widget::widget('Widget_User');

        // 获取点赞数
        $archive = $db->fetchRow($db->select('likes')->from('table.icefox_archive')->where('cid = ?', $cid));
        $likes = $archive ? $archive['likes'] : 0;

        // 检查当前用户是否已点赞
        $uid = $user->hasLogin() ? $user->uid : null;
        $ip = $this->request->getIp();
        $anonymousId = $request->get('anonymous_id');
        $mail = null;

        // 如果未登录，尝试从评论获取邮箱
        if (!$uid) {
            $userInfo = $this->getUserInfoFromComments($ip);
            if ($userInfo) {
                $mail = $userInfo['mail'];
            }
        }

        $query = $db->select()->from('table.icefox_likes')->where('cid = ?', $cid);

        if ($uid) {
            $query->where('uid = ?', $uid);
        } elseif ($mail) {
            $query->where('mail = ?', $mail);
        } elseif ($anonymousId) {
            $query->where('anonymous_id = ?', $anonymousId);
        } else {
            $query->where('ip = ?', $ip)->where('mail IS NULL')->where('anonymous_id IS NULL');
        }

        $liked = $db->fetchRow($query);

        // 获取点赞用户列表
        $likeUsers = $this->getLikeUsers($cid);

        $this->returnJson([
            'success' => true,
            'likes' => $likes,
            'isLiked' => !empty($liked),
            'likeUsers' => $likeUsers
        ]);
    }

    /**
     * 添加评论
     */
    private function addComment() {
        $request = Typecho_Request::getInstance();

        // 获取POST数据
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            $this->returnJson(['success' => false, 'message' => '无效的请求数据']);
            return;
        }

        // 验证必要字段
        $author = isset($data['author']) ? trim($data['author']) : '';
        $mail = isset($data['mail']) ? trim($data['mail']) : '';
        $text = isset($data['text']) ? trim($data['text']) : '';
        $cid = isset($data['cid']) ? intval($data['cid']) : 0;
        $coid = isset($data['coid']) ? intval($data['coid']) : 0;
        $url = isset($data['url']) ? trim($data['url']) : '';

        if (empty($author) || empty($mail) || empty($text) || empty($cid)) {
            $this->returnJson(['success' => false, 'message' => '请填写必要信息']);
            return;
        }

        // 验证邮箱格式
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $this->returnJson(['success' => false, 'message' => '邮箱格式不正确']);
            return;
        }

        $db = Typecho_Db::get();
        $ip = $this->request->getIp();
        $agent = $this->request->getAgent();
        $currentTime = time();

        try {
            // 插入评论数据
            $comment = [
                'cid' => $cid,
                'created' => $currentTime,
                'author' => $author,
                'authorId' => 0,
                'ownerId' => 0,
                'mail' => $mail,
                'url' => $url,
                'ip' => $ip,
                'agent' => $agent,
                'text' => $text,
                'type' => 'comment',
                'status' => 'approved', // 可以改为 'waiting' 需要审核
                'parent' => $coid
            ];

            $insertId = $db->query($db->insert('table.comments')->rows($comment));

            // 返回评论信息用于前端展示
            $this->returnJson([
                'success' => true,
                'message' => '评论发表成功',
                'comment' => [
                    'coid' => $insertId,
                    'author' => $author,
                    'mail' => $mail,
                    'url' => $url,
                    'text' => $text,
                    'created' => $currentTime,
                    'parent' => $coid
                ]
            ]);
        } catch (Exception $e) {
            $this->returnJson(['success' => false, 'message' => '评论发表失败：' . $e->getMessage()]);
        }
    }

    /**
     * 返回JSON响应
     */
    private function returnJson($data) {
        $this->response->setStatus(200);
        $this->response->setContentType('application/json');
        echo json_encode($data);
        exit;
    }
}