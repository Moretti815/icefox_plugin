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

        // 发布文章需要登录但不需要管理员权限
        if ($do === 'createPost') {
            if (!$user->hasLogin()) {
                $this->returnJson(['success' => false, 'message' => '请先登录']);
                return;
            }
            $this->createPost();
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
     * 创建文章
     */
    private function createPost() {
        $request = Typecho_Request::getInstance();
        $user = Typecho_Widget::widget('Widget_User');
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        // 获取表单数据
        $content = $request->get('content', '');
        $position = $request->get('position', '');
        $positionUrl = $request->get('positionUrl', '');
        $visibility = $request->get('visibility', 'public');
        $isAdvertise = $request->get('isAdvertise', '0') === '1' ? 1 : 0;

        // 验证内容
        $hasMedia = false;
        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'media_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
                $hasMedia = true;
                break;
            }
        }

        if (empty(trim($content)) && !$hasMedia) {
            $this->returnJson(['success' => false, 'message' => '请输入内容或选择图片/视频']);
            return;
        }

        try {
            // 处理文件上传
            $uploadedFiles = $this->handleMediaUpload();

            // 构建文章内容（将媒体文件以HTML形式插入）
            $postContent = $this->buildPostContent($content, $uploadedFiles);

            // 生成slug
            $slug = $this->generateSlug($content);

            // 确定文章状态
            $status = ($visibility === 'private') ? 'private' : 'publish';

            // 插入文章到contents表
            $postData = [
                'title' => $this->generateTitle($content),
                'slug' => $slug,
                'created' => time(),
                'modified' => time(),
                'text' => '<!--markdown-->' . $postContent,
                'order' => 0,
                'authorId' => $user->uid,
                'type' => 'post',
                'status' => $status,
                'password' => null,
                'commentsNum' => 0,
                'allowComment' => '1',
                'allowPing' => '1',
                'allowFeed' => '1',
                'parent' => 0
            ];

            $insertId = $db->query($db->insert('table.contents')->rows($postData));

            if (!$insertId) {
                throw new Exception('文章创建失败');
            }

            // 保存扩展信息到icefox_archive表
            $archiveData = [
                'cid' => $insertId,
                'is_top' => 0,
                'likes' => 0
            ];
            $db->query($db->insert('table.icefox_archive')->rows($archiveData));

            // 保存文章元数据（位置、广告标记）
            if (!empty($position)) {
                $this->savePostField($insertId, 'position', 'str', $position);
            }
            if (!empty($positionUrl)) {
                $this->savePostField($insertId, 'positionUrl', 'str', $positionUrl);
            }
            if ($isAdvertise) {
                $this->savePostField($insertId, 'isAdvertise', 'int', 1);
            }

            // 保存上传的文件记录
            if (!empty($uploadedFiles)) {
                $this->savePostAttachments($insertId, $uploadedFiles);
            }

            // 跳转到首页
            $options = Typecho_Widget::widget('Widget_Options');
            $homeUrl = $options->siteUrl;

            $this->returnJson([
                'success' => true,
                'message' => '发布成功',
                'cid' => $insertId,
                'redirect' => $homeUrl
            ]);

        } catch (Exception $e) {
            $this->returnJson(['success' => false, 'message' => '发布失败：' . $e->getMessage()]);
        }
    }

    /**
     * 处理媒体文件上传
     */
    private function handleMediaUpload() {
        $uploadedFiles = [];
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/' . date('Y/m/');

        // 确保上传目录存在
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($_FILES as $key => $file) {
            if (strpos($key, 'media_') !== 0 || $file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            // 验证文件类型
            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'video/mp4', 'video/webm', 'video/quicktime'
            ];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                continue;
            }

            // 生成文件名
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid('icefox_') . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $newFileName;

            // 移动文件
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $isVideo = strpos($mimeType, 'video/') === 0;
                $relativePath = '/usr/uploads/' . date('Y/m/') . $newFileName;

                $uploadedFiles[] = [
                    'name' => $file['name'],
                    'path' => $relativePath,
                    'type' => $isVideo ? 'video' : 'image',
                    'mime' => $mimeType,
                    'size' => $file['size']
                ];
            }
        }

        return $uploadedFiles;
    }

    /**
     * 构建文章内容（插入媒体）
     */
    private function buildPostContent($content, $uploadedFiles) {
        $mediaHtml = '';

        if (!empty($uploadedFiles)) {
            $images = array_filter($uploadedFiles, function($f) { return $f['type'] === 'image'; });
            $videos = array_filter($uploadedFiles, function($f) { return $f['type'] === 'video'; });

            // 添加图片
            if (!empty($images)) {
                $mediaHtml .= "\n\n";
                foreach ($images as $img) {
                    $mediaHtml .= "![图片]({$img['path']})\n";
                }
            }

            // 添加视频
            if (!empty($videos)) {
                $mediaHtml .= "\n\n";
                foreach ($videos as $video) {
                    $mediaHtml .= "<video src=\"{$video['path']}\" controls></video>\n";
                }
            }
        }

        return $content . $mediaHtml;
    }

    /**
     * 生成文章标题（从内容中提取）
     */
    private function generateTitle($content) {
        $content = trim($content);
        if (empty($content)) {
            return '无标题 - ' . date('Y-m-d H:i');
        }

        // 取第一行作为标题，最多50字符
        $firstLine = strtok($content, "\n");
        $title = mb_substr(strip_tags($firstLine), 0, 50, 'UTF-8');

        return $title ?: '无标题 - ' . date('Y-m-d H:i');
    }

    /**
     * 生成唯一slug
     */
    private function generateSlug($content) {
        $slug = 'post-' . date('YmdHis') . '-' . substr(uniqid(), -6);
        return $slug;
    }

    /**
     * 保存文章自定义字段
     */
    private function savePostField($cid, $name, $type, $value) {
        $db = Typecho_Db::get();

        $data = [
            'cid' => $cid,
            'name' => $name,
            'type' => $type,
            'str_value' => ($type === 'str') ? $value : null,
            'int_value' => ($type === 'int') ? intval($value) : 0,
            'float_value' => ($type === 'float') ? floatval($value) : 0
        ];

        $db->query($db->insert('table.fields')->rows($data));
    }

    /**
     * 保存文章附件记录
     */
    private function savePostAttachments($cid, $files) {
        $db = Typecho_Db::get();
        $user = Typecho_Widget::widget('Widget_User');

        foreach ($files as $index => $file) {
            // 将附件保存到contents表（作为attachment类型）
            $attachmentData = [
                'title' => $file['name'],
                'slug' => 'attachment-' . uniqid(),
                'created' => time(),
                'modified' => time(),
                'text' => serialize([
                    'name' => $file['name'],
                    'path' => $file['path'],
                    'size' => $file['size'],
                    'type' => $file['type'],
                    'mime' => $file['mime']
                ]),
                'order' => $index,
                'authorId' => $user->uid,
                'type' => 'attachment',
                'status' => 'publish',
                'parent' => $cid,
                'commentsNum' => 0,
                'allowComment' => '0',
                'allowPing' => '0',
                'allowFeed' => '0'
            ];

            $db->query($db->insert('table.contents')->rows($attachmentData));
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