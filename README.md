#Session引擎

#### Session类封装：
* 结构:
```
    |-Config            配置文件夹
    |-Exception         异常文件夹
    |-Lib               核心文件夹
    |-SessionInterface  接口文件夹
    |-SessionEngine.php 入口文件
    
    1.该包实现了多种session存储功能：
      (1)文件存储方式(在原默认方式下进一步封装)
      (3)Redis扩展存储方式(需安装扩展)
      (4)Db扩展存储方式(PDO数据库存储方式)
    2.每种存储方式对应在Config文件夹中的一个配置文件,配置内容请查看配置文件里的注释说明。
    3.Exception文件夹，存放本包可能抛出的所有异常文件。
      异常文件: Exception\SessionException.php
    4.Lib文件夹,存放各种存储方式核心文件。
    5.SessionInterface文件夹,存放核心文件都继承的接口文件。
    6.SessionEngine.php文件,根据用户选择，执行各种存储方式操作
```
#### 接口
通用接口
* 1 . engineStart($engineName, $configParams = NULL)  静态方法,开启引擎
```
    $engineName = 引擎名,有'Apc','Db','Normal','Redis',任君选择
    $configParams = 引擎配置数据，可以是文件路径，也可以是配置数组，配置内容查看对应的配置文件
```
* 2 . switchEngine($engineName, $configParams = NULL) 切换引擎,以后调用其他接口都是使用最后切换的引擎
```
    $engineName = 与engineStart接口参数一样
    $configParams = 与enginStart接口参数一样

```
* 3 . read($name, $position = NULL)  读取session数据
```
    $name = session名字，可以是单个session，可以是多个session组成的数组
    $position = 可选，如果输入即当前从$position位置读取session数据，
                其中Apc不需输入，
                   Db输入表名，
                   Normal输入文件夹路径，
                   Redis输入0-15的数据库index
```
* 4 . write($data, $position = NULL)  写入session数据
```
    $data = 需写入的数据，数组，例子：
            [
                ['session名字','数据','过期时间(可选，不填默认为不过期)'],
                ['session名字','数据','过期时间(可选，不填默认为不过期)'],
                ['session名字','数据','过期时间(可选，不填默认为不过期)']
            ]
    $position = 可选，如果输入即当前从$position位置写入session数据，
                其中Apc不需输入，
                    Db输入表名，
                    Normal输入文件夹路径，
                    Redis输入0-15的数据库index
```
* 5 . close()  关闭连接
```
    由于Redis和Db需要进行连接，本包对于连接的处理包括主动关闭、类回收时关闭，不进行被动关闭
```
* 6 . destroy($name, $position = NULL)  删除session数据
```
    $name = session名字，可以是单个session，可以是多个session组成的数组
    $position = 可选，如果输入即当前从$position位置删除session数据，
                其中Apc不需输入，
                    Db输入表名，
                    Normal输入文件夹路径，
                    Redis输入0-15的数据库index
```
* 7 . gc($maxLifeTime, $position = NULL)  回收过期session数据
```
    $maxLifeTime = session生存时间，单位秒，同时根据文件最新修改时间进行判断回收
    $position = 可选，如果输入即当前从$position位置回收session数据，
                其中Apc不需输入，
                    Db输入表名，
                    Normal输入文件夹路径，
                    Redis输入0-15的数据库index
```
* 8 . changeSavePosition($savePosition)  修改当前引擎使用的session位置
```
    $savePosition = Apc不需输入，
                    Db输入表名，
                    Normal输入文件夹路径，
                    Redis输入0-15的数据库index
```
* 9 . changeLostTime($lostTime)  修改全局的session过期时间
```
    $lostTime = 过期时间，单位秒，对于没有设定过期的时间的session都使用此值
```
* 10 . changeConfig($params = [])  修改当前引擎的配置
```
    $params = 可选,引擎配置数据，可以是文件路径，也可以是配置数组，配置内容查看对应的配置文件
```
* 11 . checkExpire($name, $position = NULL) 检查$name是否过期
```
    $name = session名字，可以是单个session，可以是多个session组成的数组
    $position = 可选，如果输入即当前从$position位置检查session数据，
                其中Apc不需输入，
                    Db输入表名，
                    Normal输入文件夹路径，
                    Redis输入0-15的数据库index
```
* 12 . checkExist($name, $position = NULL) 检查$name是否存在
```
    $name = session名字，可以是单个session，可以是多个session组成的数组
    $position = 可选，如果输入即当前从$position位置检查session数据，
                其中Apc不需输入，
                    Db输入表名，
                    Normal输入文件夹路径，
                    Redis输入0-15的数据库index
```
##### Redis 特有接口
* 1 flushADb($position = NULL) 清除数据库所有缓存
```
    $position = NULL,可选，如果输入0-15数据库，即清除position指定的数据库，默认当前数据库.
                可以是数组如[0,1,2,3......]
```
* 2 flushAll() 清除所有数据库的缓存

* 3 saveToDish($position = NULL) 保存当前数据库的缓存到磁盘,同步
```
    $position = NULL,可选，如果输入0-15数据库，即清除position指定的数据库，默认当前数据库.
                可以是数组如[0,1,2,3......]
```
* 4 bgSaveToDish($position = NULl)  保存当前数据库的缓存到磁盘,异步
```
    $position = NULL,可选，如果输入0-15数据库，即清除position指定的数据库，默认当前数据库.
                可以是数组如[0,1,2,3......]
```
* 5 setExpire($data, $position = NULL) 设置缓存的生存时间
```
    $data = 数组,例:['key'(缓存名字)=>10(生存时间,int,>=0)]
    $position = NULL,可选，如果输入0-15数据库，即清除position指定的数据库，默认当前数据库.

```
* 6 moveKey($data, $position = NULL) 移动缓存到其他库
```
    $data = 数组,例:['key'(缓存名字)=>10(数据库名,int,>=0 && <=15)]
    $position = NULL,可选，如果输入0-15数据库，即清除position指定的数据库，默认当前数据库.
```

     