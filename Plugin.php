<?php

namespace TypechoPlugin\Icefox;

use Typecho\Common;
use Typecho\Plugin as TypechoPlugin;
use Typecho\Plugin\Exception;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget;
use Typecho\Widget\Helper\Form\Element\Hidden;
use Typecho\Widget\Helper\Layout;
use Typecho\Db;
use Widget\Archive;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * icefox插件是icefox主题的适配插件，需搭配icefox主题使用
 * @package Icefox
 * @author 小胖脸
 * @version 1.1.6
 * @link https://xiaopanglian.com
 */

class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        if (version_compare( phpversion(), '7.0.0', '<' ) ) {
            throw new Exception('请升级到 php 7 以上');
        }
        if(version_compare(Common::VERSION,'1.2.0') < 0){
            throw new Exception('请更新typecho到1.2.0 以上');
        }
        // 添加后台头部钩子,加载视频按钮脚本
        TypechoPlugin::factory('admin/write-post.php')->bottom = [__CLASS__, 'addVideoScript'];
        TypechoPlugin::factory('admin/write-page.php')->bottom = [__CLASS__, 'addVideoScript'];

        self::checkAndCreateTable();

        // 初始化插件配置，防止进入设置页时缺少配置记录导致报错
        \Utils\Helper::configPlugin('Icefox', ['icefox_init' => '1']);

        // 注册接口路由
        \Helper::addRoute('icefox_route', '/action/icefox', Action::class, 'action');

        // 注册首页置顶功能钩子
        TypechoPlugin::factory('Widget\Archive')->indexHandle = [__CLASS__, 'indexHandle'];

        if (file_exists("admin/manage-posts.php")) {
            rename("admin/manage-posts.php", "admin/manage-posts.php.bak");
            // if(version_compare(Common::VERSION,'1.2.0') >=0){
                //挂载header.php
                copy("usr/plugins/Icefox/admin/manage-posts.php", "admin/manage-posts.php");
            // }else{
            //     //挂载header.php
            //     copy("usr/plugins/SimpleAdmin/admin/header-old.php", "admin/header.php");
            // }

        }
        // 替换默认的文章列表查询方式
        // Typecho_Plugin::factory('Widget_Contents_Post_Admin')->alloc = array(__CLASS__, 'AdminPostResetAlloc');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
    {
        // 移除路由
        \Helper::removeRoute('icefox_route');

        //还原menu.php
        // if (file_exists("var/Widget/Menu.php.bak")) {
        //     unlink("var/Widget/Menu.php");
        //     rename("var/Widget/Menu.php.bak", "var/Widget/Menu.php");
        // }
        //还原header.php
        if (file_exists("admin/manage-posts.php.bak")) {
            unlink("admin/manage-posts.php");
            rename("admin/manage-posts.php.bak", "admin/manage-posts.php");
        }
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param \Typecho\Widget\Helper\Form $form 配置面板
     * @return void
     */
    public static function config(\Typecho\Widget\Helper\Form $form)
    {
        // 提供一个隐藏配置项以便Typecho创建插件配置记录
        $form->addInput(new Hidden('icefox_init', null, '1', 'icefox_init'));

        // 获取现有友情链接
        $links = self::getLinks();

        // 将自定义配置区域放入表单内部，便于提交
        ob_start();

        // 输出友情链接管理界面
        echo '<div class="icefox-links-manager">';
        echo '<h3>友情链接管理</h3>';
        echo '<table class="typecho-list-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th width="5%">排序</th>';
        echo '<th width="15%">名称</th>';
        echo '<th width="30%">链接地址</th>';
        echo '<th width="30%">头像地址</th>';
        echo '<th width="15%">描述</th>';
        echo '<th width="5%">操作</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody id="links-tbody">';

        // 显示现有链接
        foreach ($links as $link) {
            echo '<tr data-id="' . $link['id'] . '">';
            echo '<td><input type="number" name="links[' . $link['id'] . '][sort]" value="' . $link['sort'] . '" class="text" ></td>';
            echo '<td><input type="text" name="links[' . $link['id'] . '][name]" value="' . htmlspecialchars($link['name']) . '" class="text" required></td>';
            echo '<td><input type="url" name="links[' . $link['id'] . '][url]" value="' . htmlspecialchars($link['url']) . '" class="text" required></td>';
            echo '<td><input type="url" name="links[' . $link['id'] . '][avatar]" value="' . htmlspecialchars($link['avatar']) . '" class="text"></td>';
            echo '<td><input type="text" name="links[' . $link['id'] . '][description]" value="' . htmlspecialchars($link['description']) . '" class="text"></td>';
            echo '<td><button type="button" class="btn btn-s delete-link-btn" data-id="' . $link['id'] . '" style="color:#c33;">删除</button></td>';
            echo '</tr>';
        }

        // 新增链接行
        echo '<tr id="new-link-row">';
        echo '<td><input type="number" name="new_link[sort]" value="0" class="text" ></td>';
        echo '<td><input type="text" name="new_link[name]" placeholder="名称" class="text"></td>';
        echo '<td><input type="url" name="new_link[url]" placeholder="https://example.com" class="text"></td>';
        echo '<td><input type="url" name="new_link[avatar]" placeholder="https://example.com/avatar.jpg" class="text"></td>';
        echo '<td><input type="text" name="new_link[description]" placeholder="描述" class="text"></td>';
        echo '<td><button type="button" id="add-link-btn" class="btn btn-s">添加</button></td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        // JavaScript代码：动态添加多行友情链接和AJAX删除
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var addBtn = document.getElementById("add-link-btn");
            var tbody = document.getElementById("links-tbody");
            var newRowIndex = 0;

            // 显示提示消息
            function showNotice(message, type) {
                var notice = document.createElement("div");
                notice.className = "message " + (type === "success" ? "notice" : "error");
                notice.style.cssText = "position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:9999;padding:12px 24px;border-radius:4px;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.15);";
                if (type === "success") {
                    notice.style.background = "#d4edda";
                    notice.style.color = "#155724";
                    notice.style.border = "1px solid #c3e6cb";
                } else {
                    notice.style.background = "#f8d7da";
                    notice.style.color = "#721c24";
                    notice.style.border = "1px solid #f5c6cb";
                }
                notice.textContent = message;
                document.body.appendChild(notice);
                setTimeout(function() {
                    notice.style.transition = "opacity 0.3s";
                    notice.style.opacity = "0";
                    setTimeout(function() { notice.remove(); }, 300);
                }, 2000);
            }

            // 绑定删除按钮事件
            function bindDeleteButtons() {
                document.querySelectorAll(".delete-link-btn").forEach(function(btn) {
                    btn.onclick = function() {
                        var linkId = this.getAttribute("data-id");
                        var row = this.closest("tr");
                        var linkName = row.querySelector("input[name*=\"[name]\"]").value;

                        if (!confirm("确定删除友情链接「" + linkName + "」吗？")) {
                            return;
                        }

                        btn.disabled = true;
                        btn.textContent = "删除中...";

                        fetch("/action/icefox?do=deleteFriendLink", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: "id=" + encodeURIComponent(linkId)
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.success) {
                                row.style.transition = "opacity 0.3s";
                                row.style.opacity = "0";
                                setTimeout(function() { row.remove(); }, 300);
                                showNotice(data.message || "删除成功", "success");
                            } else {
                                showNotice(data.message || "删除失败", "error");
                                btn.disabled = false;
                                btn.textContent = "删除";
                            }
                        })
                        .catch(function(error) {
                            showNotice("网络错误，请重试", "error");
                            btn.disabled = false;
                            btn.textContent = "删除";
                        });
                    };
                });
            }

            // 初始化删除按钮
            bindDeleteButtons();

            if (addBtn && tbody) {
                addBtn.addEventListener("click", function() {
                    var newRow = document.getElementById("new-link-row");
                    var nameInput = newRow.querySelector("[name=\"new_link[name]\"]");
                    var urlInput = newRow.querySelector("[name=\"new_link[url]\"]");
                    var sortInput = newRow.querySelector("[name=\"new_link[sort]\"]");
                    var avatarInput = newRow.querySelector("[name=\"new_link[avatar]\"]");
                    var descInput = newRow.querySelector("[name=\"new_link[description]\"]");

                    // 验证必填项
                    if(!nameInput.value.trim() || !urlInput.value.trim()) {
                        alert("名称和链接地址为必填项");
                        return;
                    }

                    // 创建新行（待保存的链接）
                    newRowIndex++;
                    var tr = document.createElement("tr");
                    tr.className = "pending-link-row";
                    tr.innerHTML = \'<td><input type="number" name="new_links[\' + newRowIndex + \'][sort]" value="\' + (sortInput.value || 0) + \'" class="text"></td>\' +
                        \'<td><input type="text" name="new_links[\' + newRowIndex + \'][name]" value="\' + nameInput.value.replace(/"/g, "&quot;") + \'" class="text" required></td>\' +
                        \'<td><input type="url" name="new_links[\' + newRowIndex + \'][url]" value="\' + urlInput.value.replace(/"/g, "&quot;") + \'" class="text" required></td>\' +
                        \'<td><input type="url" name="new_links[\' + newRowIndex + \'][avatar]" value="\' + (avatarInput.value || "").replace(/"/g, "&quot;") + \'" class="text"></td>\' +
                        \'<td><input type="text" name="new_links[\' + newRowIndex + \'][description]" value="\' + (descInput.value || "").replace(/"/g, "&quot;") + \'" class="text"></td>\' +
                        \'<td><button type="button" class="btn btn-s remove-pending-btn" style="color:#c33;">移除</button></td>\';

                    // 插入到新增行之前
                    tbody.insertBefore(tr, newRow);

                    // 清空输入框准备添加下一行
                    nameInput.value = "";
                    urlInput.value = "";
                    sortInput.value = "0";
                    avatarInput.value = "";
                    descInput.value = "";

                    // 绑定移除按钮事件
                    tr.querySelector(".remove-pending-btn").addEventListener("click", function() {
                        tr.remove();
                    });
                });
            }
        });
        </script>';

        echo '<style>
        .icefox-links-manager {
            margin: 20px 0;
        }
        .icefox-links-manager h3 {
            margin-bottom: 15px;
            font-size: 18px;
        }
        .icefox-links-manager table {
            width: 100%;
        }
        .icefox-links-manager table th,
        .icefox-links-manager table td {
            padding: 10px;
            text-align: left;
        }
        .icefox-links-manager input.text {
            width: 100%;
        }
        .operate-delete {
            color: #c33;
        }
        .operate-delete:hover {
            color: #a00;
        }
        .pending-link-row {
            background-color: #fffbea;
        }
        .pending-link-row td {
            border-bottom: 1px dashed #e0c36a;
        }
        #new-link-row {
            background-color: #f0f9ff;
        }
            input[type=text], input[type=password], input[type=email], textarea{
                border: 1px solid black;
                padding-block: 1px;
                padding-inline: 2px;
            }
        </style>';

        // 将输出的HTML注入表单
        $html = ob_get_clean();
        $layout = new Layout('div');
        $layout->html($html);
        $form->addItem($layout);
    }

    /**
     * 处理配置保存（确保走到 /action/plugins-edit 提交时也能写入）
     *
     * @param array $settings
     * @param bool $isInit
     * @return void
     */
    public static function configHandle(array $settings, bool $isInit)
    {
        // 保存友情链接（检查是否有链接数据提交）
        if (isset($_POST['links']) || isset($_POST['new_link']) || isset($_POST['new_links'])) {
            self::saveLinks();
            // 设置保存成功提示
            Widget::widget('Widget_Notice')->set(_t('友情链接保存成功'), 'success');
        }

        // 保存插件基础配置到 options 表
        \Widget\Plugins\Edit::configPlugin('Icefox', $settings);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param \Typecho\Widget\Helper\Form $form
     * @return void
     */
    public static function personalConfig(\Typecho\Widget\Helper\Form $form)
    {
    }

    /**
     * 插件实现方法
     *
     * @access public
     * @param $hed
     * @return string
     * @throws Typecho_Exception
     */
    public static function renderHeader($hed,$new)
    {
    }

    public static function renderFooter()
    {

    }

    // 检查并创建所需数据表
    private static function checkAndCreateTable()
    {
        $db = Db::get();
        $prefix = $db->getPrefix();

        // 创建文章扩展信息表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}icefox_archive` (
            `cid` int(10) unsigned NOT NULL, -- 文章Id
            `is_top` tinyint(1) NOT NULL DEFAULT '0', -- 是否置顶
            `likes` int(10) unsigned NOT NULL DEFAULT '0', -- 点赞总数
            PRIMARY KEY (`cid`),
            FOREIGN KEY (`cid`) REFERENCES `{$prefix}contents`(`cid`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $db->query($sql);

        // 创建点赞记录表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}icefox_likes` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `cid` int(10) unsigned NOT NULL, -- 文章Id
            `uid` int(10) unsigned DEFAULT NULL, -- 用户Id（登录用户）
            `author` varchar(150) DEFAULT NULL, -- 用户昵称
            `mail` varchar(200) DEFAULT NULL, -- 用户邮箱
            `ip` varchar(45) DEFAULT NULL, -- IP地址
            `anonymous_id` varchar(64) DEFAULT NULL, -- 匿名用户唯一标识
            `created_at` int(10) unsigned NOT NULL, -- 点赞时间
            PRIMARY KEY (`id`),
            KEY `idx_cid` (`cid`),
            KEY `idx_mail_ip` (`mail`, `ip`),
            KEY `idx_anonymous` (`anonymous_id`),
            FOREIGN KEY (`cid`) REFERENCES `{$prefix}contents`(`cid`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $db->query($sql);

        // 检查是否需要添加新字段（用于已有数据库升级）
        $columns = $db->fetchAll($db->query("SHOW COLUMNS FROM `{$prefix}icefox_likes`"));
        $columnNames = array_column($columns, 'Field');

        if (!in_array('author', $columnNames)) {
            $db->query("ALTER TABLE `{$prefix}icefox_likes` ADD COLUMN `author` varchar(150) DEFAULT NULL AFTER `uid`");
        }
        if (!in_array('mail', $columnNames)) {
            $db->query("ALTER TABLE `{$prefix}icefox_likes` ADD COLUMN `mail` varchar(200) DEFAULT NULL AFTER `author`");
        }
        if (!in_array('anonymous_id', $columnNames)) {
            $db->query("ALTER TABLE `{$prefix}icefox_likes` ADD COLUMN `anonymous_id` varchar(64) DEFAULT NULL AFTER `ip`");
            $db->query("ALTER TABLE `{$prefix}icefox_likes` ADD KEY `idx_anonymous` (`anonymous_id`)");
        }

        // 删除旧的唯一索引，因为现在通过邮箱和IP来识别
        // 先检查索引是否存在
        $indexes = $db->fetchAll($db->query("SHOW INDEX FROM `{$prefix}icefox_likes` WHERE Key_name = 'unique_like'"));
        if (!empty($indexes)) {
            $db->query("ALTER TABLE `{$prefix}icefox_likes` DROP INDEX `unique_like`");
        }

        // 创建游戏排行榜表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}icefox_game_leaderboard` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(50) NOT NULL, -- 昵称
            `email` varchar(200) NOT NULL, -- 邮箱（唯一标识）
            `score` int(10) unsigned NOT NULL DEFAULT '0', -- 分数
            `ip` varchar(45) DEFAULT NULL, -- IP地址
            `created_at` int(10) unsigned NOT NULL, -- 首次创建时间
            `updated_at` int(10) unsigned NOT NULL, -- 最后更新时间
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_email` (`email`),
            KEY `idx_score` (`score` DESC),
            KEY `idx_ip_updated` (`ip`, `updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $db->query($sql);

        // 创建友情链接表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}icefox_links` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL, -- 链接名称
            `url` varchar(500) NOT NULL, -- 链接地址
            `avatar` varchar(500) DEFAULT NULL, -- 头像地址
            `description` varchar(200) DEFAULT NULL, -- 链接描述
            `sort` int(10) unsigned NOT NULL DEFAULT '0', -- 排序
            `status` tinyint(1) NOT NULL DEFAULT '1', -- 状态：1显示 0隐藏
            `created_at` int(10) unsigned NOT NULL, -- 创建时间
            `updated_at` int(10) unsigned NOT NULL, -- 更新时间
            PRIMARY KEY (`id`),
            KEY `idx_sort_status` (`sort`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $db->query($sql);
    }

    /**
     * 重置
     */
    public static function AdminPostResetAlloc($parameter){
        $db = Db::get();

        // 执行原生SQL查询
        $query = $db->select()->from('table.contents')
            ->where('type = ?', 'post')
            ->order('cid', Db::SORT_DESC);

        // 创建自定义Widget实例
        $widget = new \Widget\Contents\Post\Admin($parameter, $query);

        // 保持分页功能
        if (isset($parameter->pageSize)) {
            $widget->pageSize = $parameter->pageSize;
        }

        return $widget;
    }

    /**
     * 首页文章列表置顶功能
     *
     * @param Archive $archive
     * @param \Typecho\Db\Query $select
     */
    public static function indexHandle($archive, $select)
    {
        $db = Db::get();
        $prefix = $db->getPrefix();

        // 关联 icefox_archive 表
        $select->join(
            $prefix . 'icefox_archive',
            $prefix . 'contents.cid = ' . $prefix . 'icefox_archive.cid',
            Db::LEFT_JOIN
        );

        // 清除原有排序,重新按置顶和时间排序
        $select->order('COALESCE(' . $prefix . 'icefox_archive.is_top, 0)', Db::SORT_DESC)
               ->order($prefix . 'contents.created', Db::SORT_DESC);
    }

    /**
     * 添加视频插入脚本
     */
    public static function addVideoScript()
    {
        $pluginUrl = Common::url('usr/plugins/Icefox/admin/video-button.js', Widget::widget('Widget_Options')->siteUrl);
        ?>
        <script src="<?php echo $pluginUrl; ?>?v=<?php echo time(); ?>"></script>
        <style>
        #wmd-video-button {
            left: 325px !important;
        }
        #wmd-video-button svg {
            width: 20px;
            height: 20px;
        }
        #wmd-video-button:hover {
            opacity: 0.8;
        }
        </style>
        <?php
    }

    /**
     * 获取所有友情链接
     */
    public static function getLinks()
    {
        $db = Db::get();
        $prefix = $db->getPrefix();

        $links = $db->fetchAll(
            $db->select()
                ->from($prefix . 'icefox_links')
                ->where('status = ?', 1)
                ->order('sort', Db::SORT_ASC)
        );

        return $links ? $links : [];
    }

    /**
     * 保存友情链接
     */
    public static function saveLinks()
    {
        $db = Db::get();
        $prefix = $db->getPrefix();
        $currentTime = time();

        // 更新现有链接
        if (isset($_POST['links']) && is_array($_POST['links'])) {
            foreach ($_POST['links'] as $id => $link) {
                if (empty($link['name']) || empty($link['url'])) {
                    continue;
                }

                $db->query(
                    $db->update($prefix . 'icefox_links')
                        ->rows([
                            'name' => $link['name'],
                            'url' => $link['url'],
                            'avatar' => $link['avatar'] ?? '',
                            'description' => $link['description'] ?? '',
                            'sort' => intval($link['sort']),
                            'updated_at' => $currentTime
                        ])
                        ->where('id = ?', $id)
                );
            }
        }

        // 添加单个新链接（从最后一行输入框）
        if (isset($_POST['new_link']) && !empty($_POST['new_link']['name']) && !empty($_POST['new_link']['url'])) {
            $newLink = $_POST['new_link'];
            $db->query(
                $db->insert($prefix . 'icefox_links')
                    ->rows([
                        'name' => $newLink['name'],
                        'url' => $newLink['url'],
                        'avatar' => $newLink['avatar'] ?? '',
                        'description' => $newLink['description'] ?? '',
                        'sort' => intval($newLink['sort']),
                        'status' => 1,
                        'created_at' => $currentTime,
                        'updated_at' => $currentTime
                    ])
            );
        }

        // 添加多个新链接（通过"添加"按钮动态添加的行）
        if (isset($_POST['new_links']) && is_array($_POST['new_links'])) {
            foreach ($_POST['new_links'] as $newLink) {
                if (empty($newLink['name']) || empty($newLink['url'])) {
                    continue;
                }

                $db->query(
                    $db->insert($prefix . 'icefox_links')
                        ->rows([
                            'name' => $newLink['name'],
                            'url' => $newLink['url'],
                            'avatar' => $newLink['avatar'] ?? '',
                            'description' => $newLink['description'] ?? '',
                            'sort' => intval($newLink['sort']),
                            'status' => 1,
                            'created_at' => $currentTime,
                            'updated_at' => $currentTime
                        ])
                );
            }
        }
    }

    /**
     * 删除友情链接
     */
    public static function deleteLink($id)
    {
        $db = Db::get();
        $prefix = $db->getPrefix();

        $db->query(
            $db->delete($prefix . 'icefox_links')
                ->where('id = ?', intval($id))
        );
    }
}


