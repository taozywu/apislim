# apislim
this is api's framwrok based on Slim !

# 目录结构

```
   YourWeb
      Apps                  -- 应用目录
         YourModule         -- 模块目录
               Common       -- 模块公共函数
               Config       -- 模块配置
               Controller   -- 模块控制器
               Logic        -- 模块逻辑
      Common                -- 全局公共函数
      Extend                -- 第三方扩展
      Library               -- 核心类库
         Common             -- 公共类
         Custom             -- 封装基类（路由、钩子、系统日志等）
         Slim               -- Slim框架
         Yclient            -- 连接Workerman框架类库
         Rest.php           -- 入口引导类文件
      Web
         index.php          -- 入口文件
```
