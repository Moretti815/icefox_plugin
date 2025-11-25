<?php

namespace TypechoPlugin\Icefox;

use Typecho\Widget;
use Typecho\Db;
use Typecho\Request;
use Typecho\Common;
use Widget\Notice;
use Widget\ActionInterface;

class Action extends Widget implements ActionInterface {
    public function action(){
        $request = Request::getInstance();
        $user = Widget::widget('Widget_User');

        // 操作类型
        $do = $request->get('do');
        if (empty($do)) {
            $this->returnJson(['success' => false, 'message' => '操作类型缺失']);
            return;
        }

        // 点赞、评论和游戏相关操作不需要管理员权限
        if ($do === 'like' || $do === 'getLikes' || $do === 'addComment' || $do === 'saveGameScore' || $do === 'getGameLeaderboard') {
            if ($do === 'like') {
                $this->toggleLike();
            } else if ($do === 'getLikes') {
                $this->getLikes();
            } else if ($do === 'addComment') {
                $this->addComment();
            } else if ($do === 'saveGameScore') {
                $this->saveGameScore();
            } else if ($do === 'getGameLeaderboard') {
                $this->getGameLeaderboard();
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
        $this->response->redirect(Common::url('admin/manage-posts.php', null));
    }

    /**
     * 设置置顶状态
     */
    public function setTop($cid, $stat){
        $db = Db::get();
        $prefix = $db->getPrefix();

        // 使用参数化防止 SQL 注入
        $cid = intval($cid);
        $stat = intval($stat);

        $sql = "INSERT INTO `{$prefix}icefox_archive` (cid, is_top, likes)
                VALUES ({$cid}, {$stat}, 0)
                ON DUPLICATE KEY UPDATE is_top = {$stat}";

        // INSERT/UPDATE 语句不返回结果集，直接执行即可
        return $db->query($sql);
    }

    /**
     * 切换点赞状态（点赞/取消点赞）
     */
    private function toggleLike() {
        $request = Request::getInstance();
        $cid = $request->get('cid');

        if (empty($cid)) {
            $this->returnJson(['success' => false, 'message' => '文章ID缺失']);
            return;
        }

        $db = Db::get();
        $prefix = $db->getPrefix();
        $user = Widget::widget('Widget_User');

        // 获取用户信息
        $uid = $user->hasLogin() ? $user->uid : null;
        $ip = $this->request->getIp();
        $anonymousId = $request->get('anonymous_id');
        $commentAuthor = $request->get('comment_author'); // 评论用户昵称
        $commentEmail = $request->get('comment_email'); // 评论用户邮箱
        $currentTime = time();
        $author = null;
        $mail = null;

        // 判断用户身份：登录用户 > 评论用户 > 匿名用户
        if ($uid) {
            // 已登录用户
            $author = $user->screenName;
            $mail = $user->mail;
        } elseif ($commentAuthor && $commentEmail) {
            // 已评论用户(有昵称和邮箱)
            $author = $commentAuthor;
            $mail = $commentEmail;
        }
        // 纯匿名用户不保存 author 和 mail，只保存 anonymous_id

        try {
            // 特殊处理：如果是评论用户,检查是否需要升级匿名点赞记录
            if ($commentEmail && $anonymousId) {
                // 检查是否存在该匿名ID的点赞记录(且没有邮箱信息)
                $anonymousLike = $db->fetchRow(
                    $db->select()->from('table.icefox_likes')
                        ->where('cid = ?', $cid)
                        ->where('anonymous_id = ?', $anonymousId)
                        ->where('mail IS NULL OR mail = ?', '')
                );

                if ($anonymousLike) {
                    // 升级匿名点赞记录：添加用户信息,清除anonymous_id
                    $db->query(
                        $db->update('table.icefox_likes')
                            ->rows([
                                'author' => $author,
                                'mail' => $mail,
                                'anonymous_id' => null  // 清除匿名ID,避免身份混乱
                            ])
                            ->where('cid = ?', $cid)
                            ->where('anonymous_id = ?', $anonymousId)
                    );
                }
            }

            // 检查用户是否已经点赞
            $query = $db->select()->from('table.icefox_likes')->where('cid = ?', $cid);

            if ($uid) {
                // 已登录用户：通过 uid 识别
                $query->where('uid = ?', $uid);
            } elseif ($commentEmail) {
                // 已评论用户：通过邮箱识别
                $query->where('mail = ?', $commentEmail);
            } elseif ($anonymousId) {
                // 匿名用户：通过 anonymous_id 识别
                $query->where('anonymous_id = ?', $anonymousId);
            } else {
                // 无任何识别信息：不允许操作
                $this->returnJson(['success' => false, 'message' => '请刷新页面后重试']);
                return;
            }

            $liked = $db->fetchRow($query);

            if ($liked) {
                // 已点赞，执行取消点赞
                $deleteQuery = $db->delete('table.icefox_likes')->where('cid = ?', $cid);

                if ($uid) {
                    $deleteQuery->where('uid = ?', $uid);
                } elseif ($commentEmail) {
                    $deleteQuery->where('mail = ?', $commentEmail);
                } elseif ($anonymousId) {
                    $deleteQuery->where('anonymous_id = ?', $anonymousId);
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
                    'anonymous_id' => ($commentEmail ? null : $anonymousId), // 评论用户不保存anonymous_id
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
        } catch (\Exception $e) {
            $this->returnJson(['success' => false, 'message' => '操作失败：' . $e->getMessage()]);
        }
    }

    /**
     * 从评论记录中查找用户信息
     */
    private function getUserInfoFromComments($ip) {
        $db = Db::get();
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
        $db = Db::get();

        $likes = $db->fetchAll(
            $db->select('author', 'mail', 'created_at')
                ->from('table.icefox_likes')
                ->where('cid = ?', $cid)
                ->order('created_at', Db::SORT_DESC)
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
        $request = Request::getInstance();
        $cid = $request->get('cid');

        if (empty($cid)) {
            $this->returnJson(['success' => false, 'message' => '文章ID缺失']);
            return;
        }

        $db = Db::get();
        $user = Widget::widget('Widget_User');

        // 获取点赞数
        $archive = $db->fetchRow($db->select('likes')->from('table.icefox_archive')->where('cid = ?', $cid));
        $likes = $archive ? $archive['likes'] : 0;

        // 检查当前用户是否已点赞
        $uid = $user->hasLogin() ? $user->uid : null;
        $commentEmail = $request->get('comment_email'); // 评论用户邮箱
        $anonymousId = $request->get('anonymous_id');

        $query = $db->select()->from('table.icefox_likes')->where('cid = ?', $cid);

        if ($uid) {
            // 已登录用户：通过 uid 识别
            $query->where('uid = ?', $uid);
        } elseif ($commentEmail) {
            // 已评论用户：通过邮箱识别
            $query->where('mail = ?', $commentEmail);
        } elseif ($anonymousId) {
            // 匿名用户：通过 anonymous_id 识别
            $query->where('anonymous_id = ?', $anonymousId);
        } else {
            // 无任何识别信息：视为新用户，不匹配任何点赞记录
            $liked = null;
            $likeUsers = $this->getLikeUsers($cid);
            $this->returnJson([
                'success' => true,
                'likes' => $likes,
                'isLiked' => false,
                'likeUsers' => $likeUsers
            ]);
            return;
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
        $request = Request::getInstance();

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

        $db = Db::get();
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
        } catch (\Exception $e) {
            $this->returnJson(['success' => false, 'message' => '评论发表失败：' . $e->getMessage()]);
        }
    }

    /**
     * 创建文章
     */
    private function createPost() {
        $request = Request::getInstance();
        $user = Widget::widget('Widget_User');
        $db = Db::get();
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
                throw new \Exception('文章创建失败');
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
            $options = Widget::widget('Widget_Options');
            $homeUrl = $options->siteUrl;

            $this->returnJson([
                'success' => true,
                'message' => '发布成功',
                'cid' => $insertId,
                'redirect' => $homeUrl
            ]);

        } catch (\Exception $e) {
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
        $db = Db::get();

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
        $db = Db::get();
        $user = Widget::widget('Widget_User');

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
     * 保存游戏分数
     */
    private function saveGameScore() {
        $request = Request::getInstance();
        $db = Db::get();
        $prefix = $db->getPrefix();

        // 获取参数
        $name = trim($request->get('name'));
        $email = trim($request->get('email'));
        $score = intval($request->get('score'));
        $gameTime = intval($request->get('gameTime')); // 游戏时长（秒）
        $checkpoints = trim($request->get('checkpoints')); // 关键检查点数据
        $signature = trim($request->get('signature')); // 签名
        $ip = $this->request->getIp();

        // 验证参数
        if (empty($name)) {
            $this->returnJson(['success' => false, 'message' => '昵称不能为空']);
            return;
        }

        if (empty($email)) {
            $this->returnJson(['success' => false, 'message' => '邮箱不能为空']);
            return;
        }

        // 验证邮箱格式
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->returnJson(['success' => false, 'message' => '邮箱格式不正确']);
            return;
        }

        if ($score <= 0) {
            $this->returnJson(['success' => false, 'message' => '分数必须大于0']);
            return;
        }

        // 防作弊验证
        $validationResult = $this->validateGameScore($score, $gameTime, $checkpoints, $signature);
        if (!$validationResult['valid']) {
            $this->returnJson(['success' => false, 'message' => $validationResult['message']]);
            return;
        }

        // 检查同一IP的更新频率限制（3秒内不能重复提交）
        $existingRecord = $db->fetchRow(
            $db->select()
                ->from($prefix . 'icefox_game_leaderboard')
                ->where('email = ?', $email)
        );

        $currentTime = time();

        if ($existingRecord) {
            // 检查IP更新频率限制
            if ($existingRecord['ip'] === $ip) {
                $timeDiff = $currentTime - $existingRecord['updated_at'];
                if ($timeDiff < 3) {
                    $this->returnJson(['success' => false, 'message' => '提交太频繁，请' . (3 - $timeDiff) . '秒后再试']);
                    return;
                }
            }

            // 更新现有记录（覆盖昵称和分数）
            $db->query(
                $db->update($prefix . 'icefox_game_leaderboard')
                    ->rows([
                        'name' => $name,
                        'score' => $score,
                        'ip' => $ip,
                        'updated_at' => $currentTime
                    ])
                    ->where('email = ?', $email)
            );

            $this->returnJson(['success' => true, 'message' => '成绩已更新', 'action' => 'updated']);
        } else {
            // 插入新记录
            $db->query(
                $db->insert($prefix . 'icefox_game_leaderboard')
                    ->rows([
                        'name' => $name,
                        'email' => $email,
                        'score' => $score,
                        'ip' => $ip,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ])
            );

            $this->returnJson(['success' => true, 'message' => '成绩已保存', 'action' => 'created']);
        }
    }

    /**
     * 获取游戏排行榜
     */
    private function getGameLeaderboard() {
        $request = Request::getInstance();
        $db = Db::get();
        $prefix = $db->getPrefix();

        // 获取参数
        $limit = intval($request->get('limit', 10));
        if ($limit <= 0 || $limit > 100) {
            $limit = 10;
        }

        // 查询排行榜
        $leaderboard = $db->fetchAll(
            $db->select('name', 'score', 'updated_at')
                ->from($prefix . 'icefox_game_leaderboard')
                ->order('score', Db::SORT_DESC)
                ->limit($limit)
        );

        $this->returnJson([
            'success' => true,
            'data' => $leaderboard
        ]);
    }

    /**
     * 验证游戏分数（防作弊）
     */
    private function validateGameScore($score, $gameTime, $checkpoints, $signature) {
        // 1. 验证游戏时长合理性
        // 假设平均速度2，每100ms增加2分，即每秒20分
        // 最小时长 = 分数 / 30（考虑速度增加，放宽到30）
        $minGameTime = $score / 30;
        if ($gameTime < $minGameTime) {
            return [
                'valid' => false,
                'message' => '游戏时间异常，请正常游戏'
            ];
        }

        // 2. 验证分数上限（防止异常高分）
        // 假设游戏最多玩10分钟，速度最高5倍，每秒最多100分
        $maxPossibleScore = 10 * 60 * 100;
        if ($score > $maxPossibleScore) {
            return [
                'valid' => false,
                'message' => '分数异常，超出合理范围'
            ];
        }

        // 3. 验证检查点数据
        if (empty($checkpoints)) {
            return [
                'valid' => false,
                'message' => '游戏数据不完整'
            ];
        }

        // 解析检查点数据（格式：距离:时间戳,距离:时间戳,...）
        $checkpointArray = explode(',', $checkpoints);

        // 根据分数动态要求检查点数量
        // 分数 < 100: 至少1个检查点
        // 分数 >= 100: 至少2个检查点
        // 分数 >= 200: 至少3个检查点
        $requiredCheckpoints = 1;
        if ($score >= 100) {
            $requiredCheckpoints = 2;
        }
        if ($score >= 200) {
            $requiredCheckpoints = 3;
        }

        if (count($checkpointArray) < $requiredCheckpoints) {
            return [
                'valid' => false,
                'message' => '游戏数据不完整'
            ];
        }

        // 验证检查点的距离递增性
        $lastDistance = 0;
        $validCheckpoints = 0;
        foreach ($checkpointArray as $checkpoint) {
            $parts = explode(':', $checkpoint);
            if (count($parts) != 2) continue;

            $distance = intval($parts[0]);
            if ($distance <= $lastDistance) {
                return [
                    'valid' => false,
                    'message' => '游戏数据异常'
                ];
            }
            $lastDistance = $distance;
            $validCheckpoints++;
        }

        // 确保有足够的有效检查点
        if ($validCheckpoints < $requiredCheckpoints) {
            return [
                'valid' => false,
                'message' => '游戏数据不完整'
            ];
        }

        // 验证最后检查点距离与提交分数的差距
        // 对于低分(<200)放宽容差,因为检查点间隔较大
        $tolerance = $score < 200 ? 200 : 150;
        if (abs($lastDistance - $score) > $tolerance) {
            return [
                'valid' => false,
                'message' => '分数数据不一致'
            ];
        }

        // 4. 验证签名
        $expectedSignature = $this->generateGameSignature($score, $gameTime, $checkpoints);
        if ($signature !== $expectedSignature) {
            return [
                'valid' => false,
                'message' => '数据签名验证失败'
            ];
        }

        return ['valid' => true];
    }

    /**
     * 生成游戏签名（与前端算法一致）
     */
    private function generateGameSignature($score, $gameTime, $checkpoints) {
        $data = $score . '|' . $gameTime . '|' . $checkpoints;
        return $this->customHash($data);
    }

    /**
     * 自定义哈希函数（与前端JavaScript实现一致）
     */
    private function customHash($str) {
        $secretKey = 'icefox_game_secret_key_2024';
        $data = $str . $secretKey;
        $hash = 0;

        // 第一轮哈希
        for ($i = 0; $i < strlen($data); $i++) {
            $char = ord($data[$i]);
            $hash = (($hash << 5) - $hash) + $char;
            $hash = $hash & 0xFFFFFFFF; // 转换为32位整数
        }

        // 转换为正数并转16进制
        $hash = abs($hash);
        $hashStr = dechex($hash);

        // 添加额外的混淆（与前端保持一致）
        for ($i = 0; $i < strlen($data); $i += 7) {
            $chunk = substr($data, $i, 7);
            $chunkHash = 0;
            for ($j = 0; $j < strlen($chunk); $j++) {
                $chunkHash = (($chunkHash << 3) - $chunkHash) + ord($chunk[$j]);
                $chunkHash = $chunkHash & 0xFFFFFFFF;
            }
            $hashStr .= dechex(abs($chunkHash));
        }

        // 确保长度一致
        $hashStr = substr($hashStr, 0, 64);
        $hashStr = str_pad($hashStr, 64, '0', STR_PAD_RIGHT);

        return $hashStr;
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
