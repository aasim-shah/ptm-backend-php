<html>

<head>
    <title>Schools</title>
</head>

<body>

<table>

    <thead>
    <tr>
        <th>Name</th>
        <th>Address</th>
        <th>Websiite</th>
        <th>Locality</th>
        <th>Post Town</th>
        <th>Post Code</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Image</th>

    </tr>
    </thead>

    <tbody>
    @foreach($schools as $data)
    <tr>
        <td>{{$data['school_name']}}</td>
        <td>{{$data['address']}}</td>
        <td>{{$data['website']}}</td>
        <td>{{$data['locality']}}</td>
        <td>{{$data['post_town']}}</td>
        <td>{{$data['post_code']}}</td>
        <td>{{$data['email']}}</td>
        <td>{{$data['phone']}}</td>
        <td>{{$data['image']}}</td>
    </tr>
    @endforeach
    </tbody>

</table>

</body>

</html>