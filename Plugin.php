<?php
use Typecho\Common;
use Typecho\Plugin;
use Typecho\Plugin\Exception;
use Typecho\Plugin\Helper;
use Typecho\Widget;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * icefox插件是icefox主题的适配插件，需搭配icefox主题使用
 * @package icefox
 * @author 小胖脸
 * @version 1.0.0
 * @link https://xiaopanglian.com
 */

// require_once 'utils/utils.php';

class Icefox_Plugin implements Typecho_Plugin_Interface
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
        // Plugin::factory('admin/header.php')->header_1011 = array('IceDog_Plugin', 'renderHeader');
        // Plugin::factory('admin/footer.php')->end_1011 = array('IceDog_Plugin', 'renderFooter');
        
        self::checkAndCreateTable();

        // 注册接口路由
        if (class_exists('Typecho\Plugin\Helper')) {
            \Typecho\Plugin\Helper::addRoute('icefox_route','/action/icefox', 'Icefox_Action', 'action');
        } elseif (class_exists('Helper')) {
            \Helper::addRoute('icefox_route','/action/icefox', 'Icefox_Action', 'action');
        }

        // 注册首页置顶功能钩子
        Typecho_Plugin::factory('Widget_Archive')->indexHandle = array(__CLASS__, 'indexHandle');

        if (file_exists("admin/manage-posts.php")) {
            rename("admin/manage-posts.php", "admin/manage-posts.php.bak");
            // if(version_compare(Common::VERSION,'1.2.0') >=0){
                //挂载header.php
                copy("usr/plugins/icefox/admin/manage-posts.php", "admin/manage-posts.php");
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
        if (class_exists('Typecho\Plugin\Helper')) {
            \Typecho\Plugin\Helper::removeRoute('icefox_route');
        } elseif (class_exists('Helper')) {
            \Helper::removeRoute('icefox_route');
        }

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
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {

    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
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
        $db = Typecho_Db::get();
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
    }

    /**
     * 重置
     */
    public static function AdminPostResetAlloc($parameter){
        $db = Typecho_Db::get();

        // 执行原生SQL查询
        $query = $db->select()->from('table.contents')
            ->where('type = ?', 'post')
            ->order('cid', Typecho_Db::SORT_DESC);

        // 创建自定义Widget实例
        $widget = new Widget_Contents_Post_Admin($parameter, $query);

        // 保持分页功能
        if (isset($parameter->pageSize)) {
            $widget->pageSize = $parameter->pageSize;
        }

        return $widget;
    }

    /**
     * 首页文章列表置顶功能
     *
     * @param Widget_Archive $archive
     * @param Typecho_Db_Query $select
     */
    public static function indexHandle($archive, $select)
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        // 关联 icefox_archive 表
        $select->join(
            $prefix . 'icefox_archive',
            $prefix . 'contents.cid = ' . $prefix . 'icefox_archive.cid',
            Typecho_Db::LEFT_JOIN
        );

        // 清除原有排序,重新按置顶和时间排序
        $select->order('COALESCE(' . $prefix . 'icefox_archive.is_top, 0)', Typecho_Db::SORT_DESC)
               ->order($prefix . 'contents.created', Typecho_Db::SORT_DESC);
    }
}
