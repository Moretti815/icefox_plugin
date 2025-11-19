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

        // 点赞相关操作不需要管理员权限
        if ($do === 'like' || $do === 'getLikes') {
            if ($do === 'like') {
                $this->toggleLike();
            } else if ($do === 'getLikes') {
                $this->getLikes();
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

        // 获取用户ID和IP
        $uid = $user->hasLogin() ? $user->uid : null;
        $ip = $this->request->getIp();
        $currentTime = time();

        try {
            // 检查用户是否已经点赞
            $where = "cid = {$cid}";
            if ($uid) {
                $where .= " AND uid = {$uid}";
            } else {
                $where .= " AND ip = '{$ip}'";
            }

            $liked = $db->fetchRow($db->select()->from('table.icefox_likes')->where($where));

            if ($liked) {
                // 已点赞，执行取消点赞
                $db->query($db->delete('table.icefox_likes')->where($where));

                // 减少点赞数
                $db->query("UPDATE `{$prefix}icefox_archive` SET likes = GREATEST(likes - 1, 0) WHERE cid = {$cid}");

                $isLiked = false;
                $message = '取消点赞成功';
            } else {
                // 未点赞，执行点赞
                $data = [
                    'cid' => $cid,
                    'uid' => $uid,
                    'ip' => $ip,
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

            // 获取最新点赞数
            $archive = $db->fetchRow($db->select('likes')->from('table.icefox_archive')->where('cid = ?', $cid));
            $likes = $archive ? $archive['likes'] : 0;

            $this->returnJson([
                'success' => true,
                'message' => $message,
                'isLiked' => $isLiked,
                'likes' => $likes
            ]);
        } catch (Exception $e) {
            $this->returnJson(['success' => false, 'message' => '操作失败：' . $e->getMessage()]);
        }
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

        $where = "cid = {$cid}";
        if ($uid) {
            $where .= " AND uid = {$uid}";
        } else {
            $where .= " AND ip = '{$ip}'";
        }

        $liked = $db->fetchRow($db->select()->from('table.icefox_likes')->where($where));

        $this->returnJson([
            'success' => true,
            'likes' => $likes,
            'isLiked' => !empty($liked)
        ]);
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