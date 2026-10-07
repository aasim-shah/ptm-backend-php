<html>

<head>
    <title>Announcements</title>
</head>

<body>

<table>

    <thead>
    <tr>
        <th>Title</th>
        <th>Description</th>
        <th>Assign</th>
        <th>AssignTo</th>
        <th>File</th>

    </tr>
    </thead>

    <tbody>
    @foreach($announcements as $data)
    <tr>
        <td>{{$data['title']}}</td>
        <td>{{$data['description']}}</td>
        <td>{{$data['assign']}}</td>
        <td>{{$data['assignto']}}</td>
        @if(count($data['file'])>0)
        <td>{{$data['file'][0]['file_url']}}</td>
        @else
        <td></td>
        @endif
    </tr>
    @endforeach
    </tbody>

</table>

</body>

</html>