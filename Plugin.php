<?php

namespace TypechoPlugin\Icefox;

use Typecho\Common;
use Typecho\Plugin as TypechoPlugin;
use Typecho\Plugin\Exception;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget;
use Typecho\Db;
use Widget\Archive;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * icefox插件是icefox主题的适配插件，需搭配icefox主题使用
 * @package Icefox
 * @author 小胖脸
 * @version 1.0.6
 * @link https://xiaopanglian.com
 */

class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
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
            throw new Exception('请更新typecho到 1.2.0 以上');
        }
        // 添加后台头部钩子,加载视频按钮脚本
        TypechoPlugin::factory('admin/write-post.php')->bottom = [__CLASS__, 'addVideoScript'];
        TypechoPlugin::factory('admin/write-page.php')->bottom = [__CLASS__, 'addVideoScript'];

        self::checkAndCreateTable();

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
        // 替换默认的文章列表查询方法
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

    // 检查并创建表
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
}

